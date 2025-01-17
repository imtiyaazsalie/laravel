<?php

namespace App\Http\Controllers\API;

use App\Enums\CoachType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Class\CreateClassRequest;
use App\Http\Requests\Class\DeleteClassRequest;
use App\Http\Requests\Class\ListClassesRequest;
use App\Http\Requests\Class\ReadClassRequest;
use App\Http\Requests\Class\UpdateClassRequest;
use App\Http\Resources\ClassResource;
use App\Models\ClassBooking;
use App\Models\ClassCoach;
use App\Models\ClassDate;
use App\Models\ClassDay;
use App\Models\Classes;
use App\Models\ClassPackage;
use App\Models\ClassToDay;
use App\Models\Package;
use App\Services\ClassDateService;
use App\Services\ClassService;
use App\Services\CrmService;
use App\Services\TagsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ClassController extends Controller
{
    public function __construct(public readonly TagsService $tagsService)
    {
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[class_type_id]', 'integer', required: false)]
    #[QueryParam('filter[coach_user_id]', 'integer', required: false)]
    #[QueryParam('filter[is_session]', 'boolean', required: false)]
    #[QueryParam('filter[is_visible_in_app]', 'boolean', required: false)]
    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    #[QueryParam('filter[is_virtual]', 'boolean', required: false)]
    #[QueryParam('filter[tag_id]', 'integer', required: false)]
    #[QueryParam('sort[location_id]', 'integer', required: false)]
    #[QueryParam('sort[class_type_id]', 'integer', required: false)]
    public function list(ListClassesRequest $request): AnonymousResourceCollection
    {
        return ClassResource::collection(
            QueryBuilder::for(Classes::class)
                ->allowedFilters([
                    AllowedFilter::exact('tenant_id', 'box_id'),
                    AllowedFilter::exact('location_id', 'box_facility_id'),
                    AllowedFilter::exact('class_type_id'),
                    AllowedFilter::exact('coach_user_id', 'headCoach.coach_id'),
                    AllowedFilter::exact('is_session'),
                    AllowedFilter::exact('is_visible_in_app'),
                    AllowedFilter::exact('is_active'),
                    AllowedFilter::exact('is_virtual'),
                    AllowedFilter::exact('tag_id', 'tags.id'),
                ])
                ->allowedSorts([
                    AllowedSort::field('class_type_id'),
                    AllowedSort::field('name', 'class_name'),
                    AllowedSort::field('location_id', 'box_facility_id'),
                ])
                ->allowedIncludes(
                    'location',
                    'classDates',
                    'daysOfWeek',
                    'tags'
                )->_paginate()
        );
    }

    public function show(ReadClassRequest $request, Classes $class): ClassResource
    {
        if ($headCoach = $class->headCoach?->user) {
            $class->setRelation('coaches', [$headCoach]);
        }

        return new ClassResource($class->loadMissing([
            'tenant', 'location', 'createdBy', 'updatedBy', 'tags', 'daysOfWeek', 'classPackages.package',
        ]));
    }

    public function store(CreateClassRequest $request): ClassResource
    {
        $class = Classes::create(
            $request->safe()->only([
                'tenant_id',
                'location_id',
                'is_session',
                'name',
                'description',
                'class_type_id',
                'start_time',
                'end_time',
                'limit',
                'booking_threshold',
                'cancellation_threshold',
                'is_display_coach_name',
                'is_free',
                'is_visible_in_app',
                'recurring_end_date',
                'meeting_url',
                'is_virtual',
                'min_booked_members_count',
                'auto_cancel_threshold_min',
            ]),
        );

        $coachFromDate = $class->isOnceOff() ? Carbon::parse($request->once_off_date) : Carbon::parse($request->safe()->collect()->get('recurring_start_date'));

        // Create class coaches
        ClassCoach::query()->create([
            'class_id' => $class->getKey(),
            'coach_id' => $request->instructor_id,
            'coach_type_id' => CoachType::HEAD_COACH,
            'is_active' => 1,
            'dt_added' => $coachFromDate,
            'dt_modified' => $coachFromDate,
        ]);

        if ($request->supporting_instructor_id) {
            ClassCoach::query()->create([
                'class_id' => $class->getKey(),
                'coach_id' => $request->supporting_instructor_id,
                'coach_type_id' => CoachType::SUPPORTING_COACH,
                'is_active' => 1,
                'dt_added' => $coachFromDate,
                'dt_modified' => $coachFromDate,
            ]);
        }

        // Link allowed packages
        if ($request->package_ids) {
            $packages = Package::query()
                ->where('is_active', '=', true)
                ->whereKey($request->package_ids)->get();

            if ($packages->count() !== count($request->package_ids)) {
                abort(400, 'Could not find some package IDs.');
            }

            foreach ($packages as $package) {
                ClassPackage::query()->create([
                    'package_id' => $package->getKey(),
                    'class_id' => $class->getKey(),
                ]);
            }
        }

        // Link tags
        if (! empty($request->tag_ids)) {
            $this->tagsService->sync($request->tag_ids, $class, auth()->user()->getAuthIdentifier());
        }

        if ($class->isOnceOff()) {
            // Create once-off class dates
            ClassDate::create([
                'class_id' => $class->getKey(),
                'class_date' => Carbon::parse($request->once_off_date),
            ]);
        } else {
            // Link class daysOfWeek
            $daysOfWeek = ClassDay::query()->whereIn('class_day_id', $request->recurring_days)->get();

            foreach ($daysOfWeek as $dayOfWeek) {
                ClassToDay::query()->create([
                    'class_id' => $class->getKey(),
                    'day_id' => $dayOfWeek->getKey(),
                ]);
            }

            // Ensure class dates for this class
            $class->ensureClassDates(Carbon::parse($request->safe()->collect()->get('recurring_start_date')));
        }

        return new ClassResource($class);
    }

    public function update(UpdateClassRequest $request, Classes $class): ClassResource
    {
        $fromDate = $request->from_date ? Carbon::parse($request->from_date)->startOfDay() : today();
        $recurringEndDate = $request->recurring_end_date ? Carbon::parse($request->recurring_end_date)->startOfDay() : null;

        // Update classDates
        if ($class->isOnceOff()) {
            $onceOffDate = Carbon::parse($request->once_off_date);
            $classDate = $class->classDates()->first();

            if (! $classDate->date->eq($onceOffDate)) {
                $classDate->date = $onceOffDate;
                $classDate->save();

                ClassCoach::query()
                    ->where('class_id', '=', $class->getKey())
                    ->where('is_active', '=', true)
                    ->update([
                        'dt_added' => $onceOffDate,
                    ]);
            }
        }

        if ($class->isRecurring()) {
            // Update ClassDays
            (new ClassService())->updateClassDays($class, $request->recurring_days, $fromDate);
            $class->refresh();

            $originalRecurringEndDate = $class->recurring_end_date;

            // Update recurring end date before generating or removing class dates
            $class->update($request->safe()->only([
                'recurring_end_date',
            ]));

            // Create the class dates for this class
            $class->ensureClassDates($fromDate);

            // Check if endDate has changed
            if ($recurringEndDate instanceof Carbon && ! $originalRecurringEndDate?->eq($recurringEndDate)) {
                (new ClassService())->removeClassDatesAfterEndDate($class, $recurringEndDate);
            }
        }

        // Update head coach
        if ($request->has('instructor_id') && ($class->headCoach->user->getKey() !== $request->instructor_id)) {
            $class->headCoach->update([
                'is_active' => false,
                'dt_modified' => $fromDate,
            ]);

            $class->coaches()->create([
                'coach_id' => $request->instructor_id,
                'coach_type_id' => CoachType::HEAD_COACH,
                'dt_added' => $fromDate->copy()->setTimeFrom(),
            ]);
        }

        // Update supporting coach
        if ($request->has('supporting_instructor_id')) {
            if ($class->supportingCoach && ((is_null($request->has('supporting_instructor_id'))) || ($class->supportingCoach->user->getKey() !== $request->supporting_instructor_id))) {
                $class->supportingCoach->update([
                    'is_active' => false,
                    'dt_modified' => $fromDate,
                ]);
            }

            if (! is_null($request->get('supporting_instructor_id')) && ($class->supportingCoach?->user->getKey() !== $request->supporting_instructor_id)) {
                $class->coaches()->create([
                    'coach_id' => $request->supporting_instructor_id,
                    'coach_type_id' => CoachType::SUPPORTING_COACH,
                    'dt_added' => $fromDate->copy()->setTimeFrom(),
                ]);
            }
        }

        // Update class packages
        if ($request->has('package_ids')) {
            // Deactivate all active records
            $class->classPackages()
                ->where('is_active', '=', true)
                ->update(['is_active' => false]);

            $packages = Package::query()
                ->where('is_active', '=', true)
                ->whereKey($request->package_ids)
                ->get();

            if ($packages->count() !== count($request->package_ids)) {
                abort(400, 'Could not find some package IDs.');
            }

            foreach ($packages as $package) {
                ClassPackage::query()->create([
                    'package_id' => $package->getKey(),
                    'class_id' => $class->getKey(),
                ]);
            }
        }

        // Update tags
        if ($request->has('tag_ids')) {
            $this->tagsService->sync($request->tag_ids, $class, auth()->user()->getAuthIdentifier());
        }

        if ($class->isRecurring()) {
            // Update the future start and end time for class dates. This is to keep data for classes that has passed
            $oldTime = $class->start_time->toTimeString().'-'.$class->end_time->toTimeString();
            $newTime = $request->start_time.'-'.$request->end_time;

            if ($oldTime != $newTime) {
                ClassDate::query()
                    ->where('class_to_dates.is_active', '=', true)
                    ->whereDate('class_to_dates.class_date', '<', $fromDate->toDateString())
                    ->where('class_to_dates.class_id', '=', $class->getKey())
                    ->whereNull('class_to_dates.start_time')
                    ->whereNull('class_to_dates.end_time')
                    ->update([
                        'class_to_dates.start_time' => $class->start_time->toTimeString(),
                        'class_to_dates.end_time' => $class->end_time->toTimeString(),
                        'class_to_dates.dt_modified' => today(),
                    ]);
            }

            // Check if limit has changed and make changes
            if ($class->limit !== $request->limit) {
                ClassDate::query()
                    ->where('class_to_dates.is_active', '=', true)
                    ->whereDate('class_to_dates.class_date', '<', $fromDate->toDateString())
                    ->where('class_to_dates.class_id', '=', $class->getKey())
                    ->whereNull('class_to_dates.class_limit')
                    ->update([
                        'class_to_dates.class_limit' => $class->limit,
                        'class_to_dates.dt_modified' => today(),
                    ]);
            }

            // Check if min_booked_members_count has changed and make changes
            if ($class->min_booked_members_count !== $request->min_booked_members_count) {
                ClassDate::query()
                    ->where('class_to_dates.is_active', '=', true)
                    ->whereDate('class_to_dates.class_date', '<', $fromDate->toDateString())
                    ->where('class_to_dates.class_id', '=', $class->getKey())
                    ->whereNull('class_to_dates.min_booked_members_count')
                    ->update([
                        'class_to_dates.min_booked_members_count' => $class->min_booked_members_count,
                        'class_to_dates.dt_modified' => today(),
                    ]);
            }

            // Check if auto_cancel_threshold_min has changed and make changes
            if ($class->auto_cancel_threshold_min !== $request->auto_cancel_threshold_min) {
                ClassDate::query()
                    ->where('class_to_dates.is_active', '=', true)
                    ->whereDate('class_to_dates.class_date', '<', $fromDate->toDateString())
                    ->where('class_to_dates.class_id', '=', $class->getKey())
                    ->whereNull('class_to_dates.auto_cancel_threshold_min')
                    ->update([
                        'class_to_dates.auto_cancel_threshold_min' => $class->auto_cancel_threshold_min,
                        'class_to_dates.dt_modified' => today(),
                    ]);
            }
        }

        // If limit has changed check if members on the waiting list need to be booked in to the class
        if ($request->limit > $class->limit) {
            $classDates = (new ClassDateService())->getUpcomingClassDatesForClass($class, $fromDate);

            foreach ($classDates as $classDate) {
                $difference = $request->limit - $class->limit;
                (new ClassService())->createClassBookingsForWaitingListWhenLimitChanges($class, $classDate, $difference);
            }
        }

        $class->update($request->safe()->only([
            'is_session',
            'name',
            'description',
            'class_limit',
            'location_id',
            'start_time',
            'end_time',
            'limit',
            'booking_threshold',
            'cancellation_threshold',
            'is_display_coach_name',
            'is_free',
            'is_visible_in_app',
            'recurring_end_date',
            'meeting_url',
            'is_virtual',
            'min_booked_members_count',
            'auto_cancel_threshold_min',
        ]));

        return new ClassResource($class->loadMissing('location'));
    }

    public function delete(DeleteClassRequest $request, Classes $class): Response
    {
        $crmService = resolve(CrmService::class);

        $fromDate = $request->collect()->get('from_date') ? Carbon::Parse($request->collect()->get('from_date')) : today();

        // Get class date for class
        $futureClassDates = (new ClassDateService())->getUpcomingClassDatesByClass($class, $fromDate);

        foreach ($futureClassDates as $futureClassDate) {
            $classBookings = ClassBooking::query()
                ->where('class_id', '=', $class->getKey())
                ->where('class_to_date_id', '=', $futureClassDate->class_to_date_id)
                ->where('class_booking_status_id', '=', 1)
                ->get();

            foreach ($classBookings as $classBooking) {
                if ($classBooking->non_member_email && is_null($classBooking->lead_member_id)) {
                    $name = $classBooking->non_member_name;
                    $email = $classBooking->non_member_email;
                } else {
                    $name = $classBooking->user->full_name;
                    $email = $classBooking->user;
                }

                $crmService->createScheduledEmailForNotification(
                    tenantOrLocation: $classBooking->class->location,
                    context: 'class_discontinued',
                    recipient: $email,
                    data: [
                        'member_name' => $name,
                        'class_name' => $futureClassDate->name,
                        'class_date' => $futureClassDate->class_date->format('Y-m-d'),
                        'class_time' => $futureClassDate->start_time.' - '.$futureClassDate->end_time,
                    ]
                );

                // Cancel class booking for member and refund sessions where needed
                (new ClassService())->cancelBookingForClassDateByUser($classBooking, auth()->user(), false);
            }

            // Deactivate class date
            $futureClassDate->update(['is_active' => false]);
        }

        // Deactivate class
        $class->update([
            'is_active' => false,
            'dt_modified' => now(),
        ]);

        $classCoaches = $class->coaches()->where('is_active', true)->get();

        foreach ($classCoaches as $classCoach) {
            $coach = $classCoach;

            // Create a scheduled email
            $crmService->createScheduledEmailForNotification(
                tenantOrLocation: $class->location,
                context: 'coach_class_discontinued',
                recipient: $coach,
                data: [
                    'coach_name' => $coach->full_name,
                    'class_name' => $class->name,
                    'class_days_or_date' => $class->getActiveClassDays(true),
                    'class_time' => $class->start_time->format('H:i').' - '.$class->end_time->format('H:i'),
                ]
            );
        }

        return response()->noContent();
    }
}
