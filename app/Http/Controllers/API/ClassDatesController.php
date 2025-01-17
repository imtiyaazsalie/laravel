<?php

namespace App\Http\Controllers\API;

use App\Enums\ClassBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassDate\BulkClassLimitChangeRequest;
use App\Http\Requests\ClassDate\BulkCoachChangeRequest;
use App\Http\Requests\ClassDate\BulkDeleteClassDatesRequest;
use App\Http\Requests\ClassDate\DeleteClassDateRequest;
use App\Http\Requests\ClassDate\ListClassDatesRequest;
use App\Http\Requests\ClassDate\MessageBookedMembersRequest;
use App\Http\Requests\ClassDate\MessageCoachesRequest;
use App\Http\Requests\ClassDate\MessageWaitingMembersRequest;
use App\Http\Requests\ClassDate\ReadClassBookingDetailsRequest;
use App\Http\Requests\ClassDate\ReadClassDateRequest;
use App\Http\Requests\ClassDate\UpdateClassDateRequest;
use App\Http\Resources\ClassBookingResource;
use App\Http\Resources\ClassBookingWaitingResource;
use App\Http\Resources\ClassDateResource;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\Location;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AccessPrivilegeService;
use App\Services\ClassBookingsService;
use App\Services\ClassBookingsWaitingService;
use App\Services\ClassDateService;
use App\Services\ClassService;
use App\Services\CrmService;
use App\Services\FinanceService;
use App\Services\TagsService;
use App\Services\TenantUserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClassDatesController extends Controller
{
    public function __construct(public ClassDateService $classDateService, public ClassBookingsService $classBookingsService, public ClassBookingsWaitingService $classBookingsWaitingService, public TagsService $tagService)
    {
    }

    #[QueryParam('filter[tenant_id', 'integer', null, false)]
    #[QueryParam('filter[location_id', 'integer', null, true)]
    #[QueryParam('filter[between', 'date', null, true)]
    #[QueryParam('filter[instructor_id', 'integer', null, false)]
    #[QueryParam('filter[supporting_instructor_id', 'integer', null, false)]
    #[QueryParam('filter[class_id', 'integer', null, false)]
    #[QueryParam('filter[is_session', 'boolean', null, false)]
    #[QueryParam('filter[tag_ids', 'array', null, false)]
    #[QueryParam('filter[package_type_id', 'array', null, false)]
    #[QueryParam('filter[package_ids', 'array', null, false)]
    public function list(ListClassDatesRequest $request)
    {
        $loggedInUserTenant = null;

        if (auth()->user()) {
            $location = Location::find($request->input('filter.location_id'));
            $loggedInUserTenant = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $location->tenant);
        }

        $classToDates = QueryBuilder::for(ClassDate::class)
            ->allowedFilters([
                AllowedFilter::callback('package_type_id', function (Builder $query, $value) {
                    $query->whereHas('class.classPackages.package', function ($q) use ($value) {
                        $q->where('package_limit_type_id', '=', $value);
                        $q->where('is_active', '=', 1);
                    });

                    $query->whereHas('class.classPackages', function ($q) {
                        $q->where('is_active', '=', 1);
                    });
                }),
                AllowedFilter::exact('package_ids', 'class.classPackages.package.package_id'),
                AllowedFilter::exact('id', 'class_to_dates.class_to_date_id'),
                AllowedFilter::exact('tenant_id', 'c.box_id'),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $query->whereBetween('class_to_dates.class_date', $value);
                }),
                AllowedFilter::exact('location_id', 'c.box_facility_id'),
                AllowedFilter::exact('is_session', 'c.is_session'),
                AllowedFilter::exact('is_visible_in_app', 'c.is_visible_in_app'),
                AllowedFilter::exact('class_id', 'c.class_id'),
                AllowedFilter::callback('instructor_id', function (Builder $query, $value) {
                    $query->whereRaw('(cc.coach_id = '.$value.' OR class_to_dates.coach_id = '.$value.')');
                }),
                AllowedFilter::callback('supporting_instructor_id', function (Builder $query, $value) {
                    $query->whereRaw('(cc2.coach_id = '.$value.' OR class_to_dates.supporting_coach_id = '.$value.')');
                }),
                AllowedFilter::callback('is_select_check_in_count', function (Builder $query, $value) {
                    if ($value) {
                        $query->addSelect(DB::raw('(SELECT COUNT(cb.class_booking_id) FROM class_bookings cb WHERE cb.class_to_date_id = class_to_dates.class_to_date_id AND cb.class_booking_status_id IN (1,5) AND cb.is_checked_in = true) AS attendeesCheckedInCount'));
                    }
                }),
                AllowedFilter::exact('is_active', 'class_to_dates.is_active')->default(true),
                AllowedFilter::callback('tag_ids', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->whereHas('tags', function ($query) use ($value) {
                            $query->whereIn('tag_id', $value);
                        })->orWhereHas('class.tags', function ($query) use ($value) {
                            $query->whereIn('tag_id', $value);
                        });
                    });
                }),
            ])
            ->from('class_to_dates as class_to_dates')
            ->join('classes as c', 'class_to_dates.class_id', '=', 'c.class_id')
            ->join('box_facility as bf', 'c.box_facility_id', '=', 'bf.box_facility_id')
            ->join('boxes as b', 'b.box_id', '=', 'bf.box_id')
            ->when(auth()->user()?->tokenCan('discovery-vitality'), function (Builder $query) {
                $query->whereHas('class.classPackages.package', function (Builder $query) {
                    $query->where('packages.package_limit_type_id', 4);
                });
            })
            ->withCoachIds()
            ->groupBy('class_to_dates.class_to_date_id')
            ->where(function ($query) {
                $query->whereRaw('class_to_dates.class_date <= DATE(c.recurring_end_date)')
                    ->orWhereNull('c.recurring_end_date');
            })
            ->when((! auth()->user() || $loggedInUserTenant?->isMember() || $loggedInUserTenant?->isLeadMember()), function ($query) {
                $query->where('c.is_visible_in_app', '=', true);
            })
            ->addSelect('c.box_facility_id AS box_facility_id')
            ->addSelect(DB::raw('(SELECT COUNT(cb.class_booking_id) FROM class_bookings cb WHERE cb.class_to_date_id = class_to_dates.class_to_date_id AND cb.class_booking_status_id IN (1,5)) AS attendanceCount'))
            ->addSelect(DB::raw('(SELECT COUNT(cbw.class_booking_waiting_id) FROM class_booking_waiting_list cbw WHERE cbw.class_to_date_id = class_to_dates.class_to_date_id AND cbw.status IN ("waiting")) AS waitingCount'))
            ->orderBy('class_to_dates.class_date')
            ->orderBy(DB::raw('IFNULL(class_to_dates.start_time, c.start_time)'))
            ->with(['tags', 'headCoach.userTenant', 'supportingCoach.userTenant', 'classBookings.user', 'classBookingWaitingList'])
            ->_paginate();

        $instructorId = (int) $request->input('filter.instructor_id');
        $supportingInstructorId = (int) $request->input('filter.supporting_instructor_id');

        if ($instructorId || $supportingInstructorId) {
            foreach ($classToDates as $index => $classToDate) {
                if ($instructorId && ! $supportingInstructorId && ($instructorId !== (int) $classToDate->coach_id)) {
                    unset($classToDates[$index]);
                } elseif ($supportingInstructorId && ! $instructorId && ($supportingInstructorId !== (int) $classToDate->supporting_coach_id)) {
                    unset($classToDates[$index]);
                } elseif ($instructorId && $supportingInstructorId && ($instructorId !== (int) $classToDate->coach_id || $supportingInstructorId !== (int) $classToDate->supporting_coach_id)) {
                    unset($classToDates[$index]);
                }
            }
        }

        return ClassDateResource::collection($classToDates);
    }

    public function show(ReadClassDateRequest $request, ClassDate $classDate): ClassDateResource
    {
        return new ClassDateResource($this->classDateService->getClassDate($classDate));
    }

    public function update(UpdateClassDateRequest $request, ClassDate $classDate): ClassDateResource
    {
        $authTenantUser = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $classDate->class->tenant_id);

        if ($authTenantUser->isGymCoach() && ! (new AccessPrivilegeService())->hasAccessToResource($authTenantUser, 'class_single_actions')) {
            if ((new ClassService())->getCoachUserForClassDate($classDate)->getKey() !== auth()->user()->getAuthIdentifier()) {
                abort(Response::HTTP_BAD_REQUEST, 'You can only update sessions that you are the trainer of.');
            }

            if (! $classDate->class->isSession()) {
                abort(Response::HTTP_BAD_REQUEST, 'You can only update sessions.');
            }
        }

        $class = $classDate->class;
        $newStartTime = Carbon::parse($request->safe()->collect()->get('start_time'), $classDate->class->tenant->timezone->zone)->setDateFrom($classDate->date);
        $newEndTime = Carbon::parse($request->safe()->collect()->get('end_time'), $classDate->class->tenant->timezone->zone)->setDateFrom($classDate->date);
        $newLimit = $request->safe()->collect()->get('limit');
        $newInstructor = User::query()->find($request->safe()->collect()->get('instructor_id'));

        // Get current values
        $currentStartTime = (new ClassService())->getClassDateStartDateTime($classDate);
        $currentEndTime = (new ClassService())->getClassDateEndDateTime($classDate);
        $currentLimit = (new ClassService())->getAttendanceLimitForClassDate($classDate);
        $currentInstructor = (new ClassService())->getCoachUserForClassDate($classDate);

        // If limit has changed check if members on the waiting list need to be booked in to the class
        if ($newLimit > $currentLimit) {
            $difference = $newLimit - $currentLimit;
            (new ClassService())->createClassBookingsForWaitingListWhenLimitChanges($class, $classDate, $difference);
        }

        // Check if the class times were changed
        $hasStartTimeChanged = $newStartTime->notEqualTo($currentStartTime);
        $hasEndTimeChanged = $newEndTime->notEqualTo($currentEndTime);

        $sendNotification = false;

        if ($hasStartTimeChanged) {
            $sendNotification = true;
        } elseif ($hasEndTimeChanged) {
            $sendNotification = true;
        } elseif ($class->is_display_coach_name && ($currentInstructor?->getKey() !== $newInstructor?->getKey())) {
            $sendNotification = true;
        }

        if ($sendNotification) {
            $classCoachChangeText = '';
            $newSupportingInstructor = $request->has('supporting_instructor_id') ? User::query()->find($request->safe()->collect()->get('supporting_instructor_id')) : null;
            $currentSupportingInstructor = (new ClassService())->getSupportingCoachUserForClassDate($classDate);

            if ($class->isDisplayCoachName() && $currentInstructor->getKey() !== $newInstructor?->getKey()) {
                $classCoachChangeText .= 'Old coach: '.$currentInstructor->full_name.'<br />';
                $classCoachChangeText .= 'New coach: '.$newInstructor?->full_name.'<br />';
            }

            $currentSupportingCoachId = $currentSupportingInstructor instanceof User ? $currentSupportingInstructor->getKey() : null;
            $newSupportingCoachId = $newSupportingInstructor instanceof User ? $newSupportingInstructor->getKey() : null;

            if ($class->isDisplayCoachName() && $newSupportingCoachId !== $currentSupportingCoachId) {
                if ($currentSupportingInstructor instanceof User) {
                    $classCoachChangeText .= 'Old supporting coach: '.$currentSupportingInstructor->full_name.'<br />';
                }

                if ($newSupportingInstructor instanceof User) {
                    $classCoachChangeText .= 'New supporting coach: '.$newSupportingInstructor->full_name.'<br />';
                }
            }

            $currentTimeText = $currentStartTime->toTimeString().' - '.$currentEndTime->toTimeString();
            $newTimeText = $newStartTime->toTimeString().' - '.$newEndTime->toTimeString();

            // Notify class bookings of coach change
            $this->classDateService->notifyClassBookingsOfClassDateChange($classDate, $currentTimeText, $newTimeText, $classCoachChangeText);
        }

        $classDate->update($request->safe()->only([
            'start_time',
            'end_time',
            'limit',
            'meeting_url',
            'instructor_id',
            'supporting_instructor_id',
            'name',
            'description',
            'min_booked_members_count',
            'auto_cancel_threshold_min',
        ]));

        if ($request->safe()->has('tag_ids')) {
            (new TagsService())->sync($request->safe()->collect()->get('tag_ids'), $classDate);
        }

        return new ClassDateResource($this->classDateService->getClassDate($classDate));
    }

    public function delete(DeleteClassDateRequest $request, ClassDate $classDate): Response
    {
        $authTenantUser = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $classDate->class->tenant_id);

        if ($authTenantUser->isGymCoach() && ! (new AccessPrivilegeService())->hasAccessToResource($authTenantUser, 'class_single_actions')) {
            if ((new ClassService())->getCoachUserForClassDate($classDate)->getKey() !== auth()->user()->getAuthIdentifier()) {
                abort(Response::HTTP_BAD_REQUEST, 'You can only delete sessions that you are the trainer of.');
            }

            if (! $classDate->class->isSession()) {
                abort(Response::HTTP_BAD_REQUEST, 'You can only delete sessions.');
            }
        }

        (new ClassService())->deactivateClassDate($classDate);

        return response()->noContent();
    }

    /**
     * Bulk delete class dates
     */
    public function bulkDelete(BulkDeleteClassDatesRequest $request): Response
    {
        $authTenantUser = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $request->tenant_id);

        $classDates = $this->classDateService->getClassDatesByIdsForTenant($request->tenant_id, $request->safe()->collect()->get('class_date_ids'));

        foreach ($classDates as $classDate) {
            if ($authTenantUser->isCoach() && ! (new AccessPrivilegeService())->hasAccessToResource($authTenantUser, 'class_bulk_actions')) {
                if (! $classDate->class->isSession() || (new ClassService())->getCoachUserForClassDate($classDate)->getKey() !== auth()->user()->getAuthIdentifier()) {
                    continue;
                }
            }

            (new ClassService())->deactivateClassDate($classDate);
        }

        return response()->noContent();
    }

    public function getClassBookingDetails(ReadClassBookingDetailsRequest $request, ClassDate $classDate): JsonResponse
    {
        $class = $classDate->class;
        $canAthleteBookForClassDate = (new ClassService())->canAthleteBookForClassDate($classDate, auth()->user());

        $classBookings = ClassBooking::query()
            ->select('class_bookings.*')
            ->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->join('classes', 'classes.class_id', '=', 'class_bookings.class_id')
            ->addSelect(DB::raw('(SELECT COUNT(cb.class_booking_id) = 0 FROM class_bookings as cb
                    INNER JOIN classes as c ON cb.class_id = c.class_id
                    WHERE cb.user_id = class_bookings.user_id
                        AND c.box_facility_id = classes.box_facility_id
                        AND cb.class_booking_id != class_bookings.class_booking_id
                        AND cb.class_booking_status_id = '.ClassBookingStatus::BOOKED->value.'
            ) as is_first_booking_at_location'))
            ->where('class_bookings.class_to_date_id', '=', $classDate->getKey())
            ->whereIn('class_bookings.class_booking_status_id', [ClassBookingStatus::BOOKED->value, ClassBookingStatus::CANCELLED_AFTER_THRESHOLD->value, ClassBookingStatus::NO_SHOW->value])
            ->get()
            ->transform(function ($classBooking) {
                if ($classBooking->userTenant) {
                    $classBooking->userTenant->is_overdue = $classBooking->userTenant->isMember() && (new FinanceService())->getAmountOutstanding($classBooking->userTenant) > 0;
                }

                return $classBooking;
            });

        $waitingListBookings = (new ClassBookingsWaitingService())->getWaitingForClassAndDate($class, $classDate);

        $classBookings->loadMissing('userPackage.package.tenant', 'userTenant.user.injuries', 'createdBy', 'updatedBy', 'leadMember');

        $classBookings->transform(function ($booking) {

            $booking->userPackage?->unsetRelation('userTenant');

            $booking->is_first_booking_at_location = (bool) $booking->is_first_booking_at_location;

            $booking->is_user_overdue_at_location = (bool) $booking->is_user_overdue_at_location;

            return $booking;
        });

        $myClassBooking = $classBookings->where('class_booking_status_id', ClassBookingStatus::BOOKED->value)
            ->where('user_id', auth()->user()->getAuthIdentifier())
            ->first();

        $classBookingWaiting = $waitingListBookings->where('user_id', auth()->user()->getAuthIdentifier())->first();

        request()->merge([
            'internalAppend' => 'withoutClass,withoutTenant,withoutPackage,withoutLocations',
        ]);

        return response()->json([
            'canBook' => $canAthleteBookForClassDate,
            'bookings' => $classBookings->isNotEmpty() ? ClassBookingResource::collection($classBookings) : null,
            'waitingList' => $waitingListBookings->isNotEmpty() ? ClassBookingWaitingResource::collection($waitingListBookings) : null,
            'booking' => $myClassBooking ? new ClassBookingResource($myClassBooking) : null,
            'waitingListBooking' => $classBookingWaiting ? new ClassBookingWaitingResource($classBookingWaiting) : null,
        ]);
    }

    public function sendMessageToCoach(MessageCoachesRequest $request, ClassDate $classDate): Response
    {
        $crmService = resolve(CrmService::class);

        $class = $classDate->class;
        $className = (new ClassService())->getClassDateName($classDate);
        $classTime = (new ClassService())->getClassDateStartDateTime($classDate)->format('H:i').' - '.(new ClassService())->getClassDateEndDateTime($classDate)->format('H:i');

        // Get class coaches
        $headCoach = (new ClassService())->getCoachUserForClassDate($classDate);
        $supportingCoach = (new ClassService())->getSupportingCoachUserForClassDate($classDate);

        $coaches = [];

        if ($headCoach) {
            $coaches[] = $headCoach;
        }

        if ($supportingCoach) {
            $coaches[] = $supportingCoach;
        }

        // Notify coaches
        $user = auth()->user();

        foreach ($coaches as $coach) {
            $crmService->createScheduledEmailForNotification(
                tenantOrLocation: $class->location,
                context: 'coach_message',
                recipient: $coach,
                data: [
                    'member_name' => $user->name,
                    'member_surname' => $user->surname,
                    'member_message' => nl2br($request->message),
                    'class_name' => $className,
                    'class_time' => $classTime,
                    'class_booking_date' => $classDate->buildDateTime()->format('D, d F Y'),
                ],
                replyTo: $user->email,
                queue: 'high'
            );
        }

        return response()->noContent();
    }

    public function sendMessageToBookedMembers(MessageBookedMembersRequest $request, ClassDate $classDate): Response
    {
        (new ClassService())->sendMessageToClass($classDate, $request->safe()->collect()->get('message'), 'bookedMembers');

        return response()->noContent();
    }

    public function sendMessageToWaitingMembers(MessageWaitingMembersRequest $request, ClassDate $classDate): Response
    {
        (new ClassService())->sendMessageToClass($classDate, $request->safe()->collect()->get('message'), 'waitingListMembers');

        return response()->noContent();
    }

    public function bulkCoachChange(BulkCoachChangeRequest $request): Response
    {
        $authTenantUser = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $request->tenant_id);
        $classDates = $this->classDateService->getClassDatesByIdsForTenant($request->tenant_id, $request->safe()->collect()->get('class_date_ids'));

        $headCoachUserBoxMembership = TenantUser::query()
            ->active()
            ->where('user_id', $request->instructor_id)
            ->where('box_id', $request->tenant_id)
            ->first();

        if (! $headCoachUserBoxMembership) {
            abort(400, 'Instructor not found');
        }

        $newHeadCoach = $headCoachUserBoxMembership->user;

        foreach ($classDates as $classDate) {
            if ($authTenantUser->isCoach() && ! (new AccessPrivilegeService())->hasAccessToResource($authTenantUser, 'class_bulk_actions')) {
                if (! $classDate->class->isSession() || (new ClassService())->getCoachUserForClassDate($classDate)->getKey() !== auth()->user()->getAuthIdentifier()) {
                    continue;
                }
            }

            $classCoachChangeText = '';
            $class = $classDate->class;
            $currentHeadCoach = (new ClassService())->getCoachUserForClassDate($classDate);

            if ($class->is_display_coach_name && $currentHeadCoach->getKey() !== $newHeadCoach->getKey()) {
                $classCoachChangeText = 'Old coach: '.$currentHeadCoach->name.' '.$currentHeadCoach->surname.'<br />';
                $classCoachChangeText .= 'New coach: '.$newHeadCoach->name.' '.$newHeadCoach->surname.'<br />';
            }

            // Notify class bookings of coach change
            (new ClassService())->notifyClassBookingsOfClassDateChange($classDate, null, null, $classCoachChangeText);

            $classDate->update(['coach_id' => $newHeadCoach->getKey()]);
        }

        return response()->noContent();
    }

    public function bulkChangeLimit(BulkClassLimitChangeRequest $request): Response
    {
        $limit = $request->safe()->collect()->get('limit');
        $authTenantUser = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $request->tenant_id);
        $classDates = $this->classDateService->getClassDatesByIdsForTenant($request->tenant_id, $request->safe()->collect()->get('class_date_ids'));

        foreach ($classDates as $classDate) {
            if ($authTenantUser->isCoach() && ! (new AccessPrivilegeService())->hasAccessToResource($authTenantUser, 'class_bulk_actions')) {
                if (! $classDate->class->isSession() || (new ClassService())->getCoachUserForClassDate($classDate)->getKey() !== auth()->user()->getAuthIdentifier()) {
                    continue;
                }
            }

            if ($classDate->class->isOnceOff()) {
                $classDate->class->update(['class_limit' => $limit]);
            } else {
                $classDate->update(['class_limit' => $limit]);
            }
        }

        return response()->noContent();
    }
}
