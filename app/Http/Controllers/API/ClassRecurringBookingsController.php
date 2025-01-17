<?php

namespace App\Http\Controllers\API;

use App\Enums\CoachType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassRecurringBookings\CreateRecurringBookingRequest;
use App\Http\Requests\ClassRecurringBookings\DeactivateRecurringBookingRequest;
use App\Http\Requests\ClassRecurringBookings\DeleteRecurringBookingRequest;
use App\Http\Requests\ClassRecurringBookings\ListRecurringBookingsRequest;
use App\Http\Requests\ClassRecurringBookings\ReactivateRecurringBookingRequest;
use App\Http\Requests\ClassRecurringBookings\UpdateRecurringBookingRequest;
use App\Http\Resources\ClassRecurringBookingResource;
use App\Models\ClassDay;
use App\Models\Classes;
use App\Models\ClassRecurringBooking;
use App\Models\TenantUser;
use App\Services\ClassBookingsService;
use App\Services\ClassService;
use App\Services\RecurringBookingService;
use App\Services\TenantUserService;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClassRecurringBookingsController extends Controller
{
    public function __construct(public RecurringBookingService $service)
    {
    }

    #[QueryParam('filter[location]', 'integer', null, false)]
    #[QueryParam('filter[coach_id]', 'integer', null, false)]
    #[QueryParam('filter[status]', 'boolean', null, false)]
    #[QueryParam('filter[is_session]', 'boolean', null, false)]
    #[QueryParam('filter[is_session]', 'boolean', null, false)]
    #[QueryParam('sorts', 'string', 'name,surname', false)]
    public function list(ListRecurringBookingsRequest $request)
    {
        $classRecurringBookings = QueryBuilder::for(ClassRecurringBooking::class)
            ->from('class_recurring_bookings', 'crb')
            ->join('classes as c', 'crb.class_id', '=', 'c.class_id')
            ->join('boxes as b', 'b.box_id', '=', 'c.box_id')
            ->join('users as u', 'crb.user_id', '=', 'u.user_id')
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'b.box_id'),
                AllowedFilter::exact('status', 'crb.active')->default(true),
                AllowedFilter::callback('location_id', function (Builder $query, $value) {
                    $query->join('user_to_facility as fm', 'u.user_id', '=', 'fm.user_id')
                        ->where('fm.box_facility_id', '=', $value)
                        ->where('fm.end_date', '>', today());
                }),
                AllowedFilter::callback('coach_id', function (Builder $query, $value) {
                    $query->join('class_coaches as cc', 'c.class_id', '=', 'cc.class_id')
                        ->where('cc.coach_id', '=', $value)
                        ->where('cc.coach_type_id', '=', CoachType::HEAD_COACH->value)
                        ->where('cc.is_active', '=', true);
                }),
                AllowedFilter::exact('is_session', 'c.is_session'),

            ])
            ->allowedIncludes('daysOfWeek')
            ->defaultSorts('u.name', 'u.surname')
            ->_paginate();

        return ClassRecurringBookingResource::collection($classRecurringBookings);
    }

    public function store(CreateRecurringBookingRequest $request): ClassRecurringBookingResource|JsonResponse
    {
        $class = Classes::query()->findOrFail($request->safe()->collect()->get('class_id'));
        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($request->safe()->collect()->get('user_id'), $class->tenant_id);
        $daysOfTheWeek = $request->safe()->collect()->get('days_of_the_week');

        $errors = [];

        if (! $class instanceof Classes) {
            $errors['class'] = 'Class is required';
        }

        if (! $userBoxMembership instanceof TenantUser) {
            $errors['user'] = 'User box-membership is required';
        }

        if (! isset($daysOfTheWeek) || $daysOfTheWeek == '') {
            $errors['daysOfTheWeek'] = 'Days of the week is required';
        }

        if (count($errors) > 0) {
            return response()->json($errors, 400);
        }

        $classRecurringBooking = ClassRecurringBooking::query()->create([
            'class_id' => $class->getKey(),
            'user_id' => $request->safe()->collect()->get('user_id'),
            'active' => true,
            'dt_deactivate' => $request->safe()->has('ending_on_date') ? $request->safe()->collect()->get('ending_on_date') : null,
        ]);

        foreach (@$daysOfTheWeek as $day) {
            $classRecurringBooking->daysOfWeek()->attach($day);
        }

        (new ClassService())->disableSendEmails()->ensureBookingsForRecurringBooking($classRecurringBooking);

        return new ClassRecurringBookingResource($classRecurringBooking->load(['daysOfWeek', 'class.daysOfWeek']));
    }

    public function update(UpdateRecurringBookingRequest $request, ClassRecurringBooking $booking): ClassRecurringBookingResource
    {
        $originalDaysOfWeek = [];
        $deletedDaysOfWeek = [];
        $newDaysOfWeek = [];

        foreach ($booking->daysOfWeek()->get() as $dayOfWeek) {
            $originalDaysOfWeek[] = $dayOfWeek->getKey();
        }

        foreach ($originalDaysOfWeek as $originalDayOfWeekId) {
            if (! in_array($originalDayOfWeekId, $request->safe()->collect()->get('days_of_the_week'))) {
                $deletedDaysOfWeek[] = $originalDayOfWeekId;
            }
        }

        foreach ($request->safe()->collect()->get('days_of_the_week') as $dayOfWeekId) {
            if (! in_array($dayOfWeekId, $originalDaysOfWeek)) {
                $newDaysOfWeek[] = $dayOfWeekId;
            }
        }

        if (! empty($deletedDaysOfWeek)) {
            foreach ($deletedDaysOfWeek as $dow) {
                $dayOfWeek = ClassDay::query()->find($dow);

                $booking->daysOfWeek()->detach($dayOfWeek);

                $booking->load('daysOfWeek');
            }

            (new ClassService())->deleteFutureBookingsForRecurringBookingDaysOfWeek($booking, $deletedDaysOfWeek);
        }

        if (! empty($newDaysOfWeek)) {
            foreach ($newDaysOfWeek as $dow) {
                $dayOfWeek = ClassDay::query()->find($dow);

                $booking->daysOfWeek()->attach($dayOfWeek);

                $booking->load('daysOfWeek');
            }

            (new ClassService())->ensureBookingsForRecurringBooking($booking);
        }

        // Get current deactivateOn date
        $currentDeactivatedOnDate = $booking->dt_deactivate;
        $booking->update(['dt_deactivate' => $request->safe()->collect()->get('ending_on_date')]);

        if (! $currentDeactivatedOnDate && ! $request->safe()->collect()->get('ending_on_date')) {
            // endDate is NOT there and also hasn't been updated
            (new ClassService())->ensureBookingsForRecurringBooking($booking);
        } elseif (! $currentDeactivatedOnDate && $request->safe()->collect()->get('ending_on_date')) {
            // endDate was NOT there but has been set now
            (new ClassService())->deleteFutureClassBookingsForRecurringBookingAfterEndDate($booking, $request->safe()->collect()->get('ending_on_date'));
        } elseif ($currentDeactivatedOnDate && ! $request->safe()->collect()->get('ending_on_date')) {
            // endDate was there but has been removed
            (new ClassService())->ensureBookingsForRecurringBooking($booking);
        } elseif ($currentDeactivatedOnDate && $request->safe()->collect()->get('ending_on_date')) {
            // endDate was there but something has been changed (daysOfTheWeek or endDate)

            // Check weather to generate or delete class bookings
            if ($request->safe()->collect()->get('ending_on_date') < $currentDeactivatedOnDate) {
                (new ClassService())->deleteFutureClassBookingsForRecurringBookingAfterEndDate($booking, $request->safe()->collect()->get('ending_on_date'));
            } else {
                (new ClassService())->ensureBookingsForRecurringBooking($booking);
            }
        }

        return new ClassRecurringBookingResource($booking->load('daysOfWeek'));
    }

    public function deactivate(DeactivateRecurringBookingRequest $request, ClassRecurringBooking $booking): ClassRecurringBookingResource
    {
        $booking->update([
            'active' => false,
            'dt_deactivate' => new DateTime(),
        ]);

        $periodFromWhichToStartDeactivating = new DateTime("+ {$booking->class->cancellation_threshhold} minutes");

        // These bookings will not be canceled but deleted
        $classBookings = (new ClassBookingsService())->getRecurringClassBookingsToCancel($periodFromWhichToStartDeactivating, $booking->class, $booking->user);

        foreach ($classBookings as $classBooking) {
            (new ClassService())->deleteBookingForClassDateByUser($classBooking, $request->user());
        }

        return new ClassRecurringBookingResource($booking);
    }

    public function reactivate(ReactivateRecurringBookingRequest $request, ClassRecurringBooking $booking): ClassRecurringBookingResource
    {
        $booking->update([
            'active' => true,
            'dt_deactivate' => null,
        ]);

        (new ClassService())->ensureBookingsForRecurringBooking($booking);

        return new ClassRecurringBookingResource($booking);
    }

    public function delete(DeleteRecurringBookingRequest $request, ClassRecurringBooking $booking): JsonResponse
    {
        $periodFromWhichToStartDeactivating = new DateTime("+ {$booking->class->cancellation_threshhold} minutes");

        // These bookings will not be canceled but deleted
        $classBookings = (new ClassBookingsService())->getRecurringClassBookingsToCancel($periodFromWhichToStartDeactivating, $booking->class, $booking->user);
        foreach ($classBookings as $classBooking) {
            (new ClassService())->deleteBookingForClassDateByUser($classBooking, $request->user());
        }

        $booking->daysOfWeek()->detach();
        $booking->delete();

        return response()->json();
    }
}
