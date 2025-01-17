<?php

namespace App\Http\Controllers\API;

use App\Enums\ClassBookingStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassBooking\CancelBookingRequest;
use App\Http\Requests\ClassBooking\CheckInRequest;
use App\Http\Requests\ClassBooking\ClassBookingShowRequest;
use App\Http\Requests\ClassBooking\CreateClassBookingRequest;
use App\Http\Requests\ClassBooking\GetClassBookingsByPackageRequest;
use App\Http\Requests\ClassBooking\GetClassBookingStatsRequest;
use App\Http\Requests\ClassBooking\GetMyClassBookingsRequest;
use App\Http\Requests\ClassBooking\MessageAthleteRequest;
use App\Http\Requests\ClassBooking\NoShowRequest;
use App\Http\Requests\ClassDate\ListClassBookingsRequest as ClassDateListClassBookingsRequest;
use App\Http\Resources\ClassBookingResource;
use App\Models\AttendanceRecord;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\AccessPrivilegeService;
use App\Services\AttendanceRecordService;
use App\Services\ClassBookingsService;
use App\Services\ClassService;
use App\Services\CRM\DiscoveryNotificationsService;
use App\Services\CrmService;
use App\Services\InvoiceService;
use App\Services\TenantUserService;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

class ClassBookingController extends Controller
{
    #[QueryParam('filter[user_type_id]', 'integer', null, false)]
    #[QueryParam('filter[location_id]', 'integer', null, false)]
    #[QueryParam('filter[tenant_id]', 'integer', null, true)]
    #[QueryParam('filter[user_id]', 'integer', null, false)]
    #[QueryParam('filter[status]', 'array', null, false)]
    #[QueryParam('filter[class_date_id]', 'integer', null, false)]
    #[QueryParam('filter[between]', 'array', null, false)]
    public function list(ClassDateListClassBookingsRequest $request)
    {
        $classBookings = QueryBuilder::for(ClassBooking::class)
            ->select('class_bookings.*')
            ->join('class_to_dates', 'class_bookings.class_to_date_id', '=', 'class_to_dates.class_to_date_id')
            ->join('classes', 'class_to_dates.class_id', '=', 'classes.class_id')
            ->allowedFilters([
                AllowedFilter::exact('user_id', 'class_bookings.user_id'),
                AllowedFilter::exact('lead_member_id', 'class_bookings.lead_member_id'),
                AllowedFilter::exact('tenant_id', 'classes.box_id'),
                AllowedFilter::callback('user_type_id', function (Builder $query, $value) {
                    $query->join('user_to_box', function ($join) {
                        $join->on('user_to_box.box_id', '=', 'classes.box_id')
                            ->on('user_to_box.user_id', '=', 'class_bookings.user_id');
                    })->where('user_to_box.user_type_id', $value);
                }),
                AllowedFilter::exact('location_id', 'classes.box_facility_id'),
                AllowedFilter::exact('class_date_id', 'class_bookings.class_to_date_id'),
                AllowedFilter::callback('status', function (Builder $query, $value) {
                    if (is_string($value)) {
                        $value = [$value];
                    }

                    if (in_array(ClassBookingStatus::CHECKED_IN->value, $value)) {
                        $query->where('class_bookings.is_checked_in', '=', 1);

                        if (! in_array(ClassBookingStatus::BOOKED->value, $value)) {
                            $value[] = ClassBookingStatus::BOOKED->value;
                        }
                    }

                    $query->whereIn('class_bookings.class_booking_status_id', $value);
                }),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $query->whereBetween('class_to_dates.class_date', $value);
                }),
            ])
            ->allowedIncludes('coronavirusQuestionaireResult')
            ->with(['userTenant', 'classDate.headCoach', 'classDate.supportingCoach', 'createdBy', 'updatedBy'])
            ->allowedSorts([
                AllowedSort::field('class_booking_id', 'class_bookings.class_booking_id'),
                AllowedSort::callback('class_date_time', function (Builder $query, $descending) {

                    $sort = $descending ? SortDirection::DESCENDING : SortDirection::ASCENDING;

                    $query->addSelect(
                        DB::raw("CONCAT_WS(' ', class_to_dates.class_date, IF (class_to_dates.start_time IS NULL, classes.start_time, class_to_dates.start_time)) as class_date_time")
                    );

                    $query->orderBy('class_date_time', $sort)
                        ->orderBy('class_booking_id');

                }),
            ])
            ->defaultSort('class_booking_id')
            ->_paginate();

        return ClassBookingResource::collection($classBookings);
    }

    public function show(ClassBookingShowRequest $request, ClassBooking $classBooking): ClassBookingResource
    {
        return new ClassBookingResource($classBooking->loadMissing(['classDate.headCoach', 'classDate.supportingCoach', 'userPackage']));
    }

    public function store(CreateClassBookingRequest $request)
    {
        request()->merge([
            'internalAppend' => 'withoutTenant',
        ]);

        if ($request->user()->tokenCan('discovery-vitality')) {
            $classDate = ClassDate::find($request->input('class_date_id'));

            $box = $classDate->class->tenant;
            $location = $classDate->class->location;
            $timezone = $location->timezone ? $location->timezone : $box->timezone;

            // Box booking threshold check
            $bookingThresholdDate = new DateTime('now', new \DateTimeZone($timezone->zone));
            $bookingThresholdDate->modify('+'.$box->booking_threshold.' days');

            // Get classTime
            $startTime = (new ClassService())->getClassDateStartDateTime($classDate);
            $classDateTime = clone $classDate->date;
            $classDateTime->setTimezone(new \DateTimeZone($timezone->zone));
            $classDateTime->setTime($startTime->format('H'), $startTime->format('i'), $startTime->format('s'));

            if ($classDateTime > $bookingThresholdDate) {
                abort(400, 'You cannot book this far in advance');
            }

            $canMemberBookOrErrorMessage = (new ClassService())->canPackageLeadMemberBookForClassDate($classDate, null, true);

            if (is_string($canMemberBookOrErrorMessage)) {
                abort(400, $canMemberBookOrErrorMessage);
            }

            $leadMember = LeadMember::where('box_facility_id', $location->getKey())
                ->where('user_id', $request->input('user_id'))
                ->first();

            $classBooking = (new ClassService())->createClassBookingForClassDateAndLeadByUser($classDate, $leadMember);

            (new DiscoveryNotificationsService())->sendBookingConfirmationToMember($classBooking);
            (new DiscoveryNotificationsService())->sendBookingConfirmationToCoach($classBooking);

            request()->merge([
                'internalAppend' => 'withoutTenant',
            ]);

            return new ClassBookingResource(
                $classBooking->loadMissing(['classDate', 'userPackage'])
            );
        }

        $classBooking = null;
        $classDate = ClassDate::query()->findOrFail($request->safe()->collect()->get('class_date_id'));

        $authTenant = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $classDate->class->tenant);

        if (! $authTenant) {
            abort(400, 'You are not a user at this tenant.');
        }

        if ($authTenant->isMember() || $authTenant->isLeadMember()) {

            $userPackage = UserPackage::query()->find($request->safe()->collect()->get('user_package_id'));

            if ($request->user_id != $authTenant->user_id) {
                abort(403, 'You are not allowed to book a class for another user.');
            }

            $now = new DateTime('now', new \DateTimeZone($authTenant->tenant->timezone->zone));

            // Box booking threshold check
            $bookingThresholdDate = clone $now;
            $bookingThresholdDate->modify('+'.$authTenant->tenant->booking_threshold.' days');
            $bookingThresholdDate->modify('-1 hour');

            // Get start and end dateTime
            $startDateTime = (new ClassService())->getClassDateStartDateTime($classDate);

            if ($startDateTime > $bookingThresholdDate) {
                abort(400, 'You cannot book this far in advance.');
            }

            $classBookingErrorMessageOrUserPackage = (new ClassService())->canAthleteBookForClassDate($classDate, $request->user(), false, true, $userPackage);

            if (! $classBookingErrorMessageOrUserPackage instanceof UserPackage) {
                abort(400, $classBookingErrorMessageOrUserPackage);
            }

            if (! $request->has('booking_threshold_bypass')) {
                $isPastBookingThreshold = (new ClassBookingsService())->bookingThreshold($classDate);
                if ($isPastBookingThreshold) {
                    abort(400, 'Past booking threshold.');
                }
            }

            // Create class booking for user
            $classBooking = (new ClassService())->createClassBookingForClassDateAndAthleteByUser($classDate, $request->user(), $request->user(), null, false, $userPackage);
        } else {
            // Coach booking a member, staff or non-member into a class
            $userBoxMembershipId = $request->get('user_id');
            $nonMember = $request->input('non_member');

            if (! $userBoxMembershipId && ! $nonMember) {
                abort(400, 'user_id or non_member is required.');
            }

            if ($userBoxMembershipId) {
                $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($request->get('user_id'), $classDate->class->tenant);

                if (! $userBoxMembership instanceof TenantUser) {
                    abort(400, 'User Tenant could not be found.');
                }

                $user = $userBoxMembership->user;

                if ($userBoxMembership->isMember()) {
                    $classBookingErrorMessageOrUserPackage = (new ClassService())->canCoachBookAthleteForClassDate($classDate, $user, true);

                    if (is_string($classBookingErrorMessageOrUserPackage) && ! $classBookingErrorMessageOrUserPackage instanceof UserPackage) {
                        abort(400, $classBookingErrorMessageOrUserPackage);
                    }

                    // Get sessions remaining
                    $usersCurrentBoxFacility = (new TenantUserService())->getLocationUserByTenant($userBoxMembership->user, $userBoxMembership->tenant)?->location;
                    $sessionsRemaining = $usersCurrentBoxFacility instanceof Location ? (new ClassService())->getSessionsRemainingForUserPackageForDate($classBookingErrorMessageOrUserPackage, $usersCurrentBoxFacility, $classDate->class_date) : null;

                    if (($classBookingErrorMessageOrUserPackage->package->package_limit === 0 && $sessionsRemaining <= 0) || $sessionsRemaining <= 0) {
                        $hasSessionsRemaining = false;
                    } else {
                        $hasSessionsRemaining = true;
                    }

                    if (! $hasSessionsRemaining && ! (new AccessPrivilegeService())->hasAccessToResource($authTenant, 'class_booking_overbook_member') && ! $classDate->class->is_free) {
                        abort(Response::HTTP_UNAUTHORIZED, 'Access denied.');
                    }

                    $classBooking = (new ClassService())->createClassBookingForClassDateAndAthleteByUser($classDate, $user, $request->user());
                } elseif ($userBoxMembership->isLeadMember()) {
                    $leadMember = LeadMember::where('box_facility_id', $classDate->class->location_id)
                        ->where('user_id', $request->input('user_id'))
                        ->firstOrFail();

                    if ((new ClassService())->getBookingForLeadMemberAndClassDate($leadMember, $classDate)) {
                        abort(400, 'Lead member already has a booking for this class');
                    }

                    $classBooking = (new ClassService())->createClassBookingForClassDateAndLeadByUser($classDate, $leadMember);
                } else {
                    if ((new ClassBookingsService())->getBookingForAthleteAndClassDate($user, $classDate)) {
                        abort(400, 'Staff member already has a booking for this class');
                    }

                    $classBooking = (new ClassService())->createClassBookingForClassDateAndCoachByUser($classDate, $user, $request->user());
                }
            } elseif ($nonMember) {
                // Skip if email is not set for user
                if (empty($nonMember['email'])) {
                    abort(400, 'Email is a required field.');
                }

                $name = $nonMember['name'] ?? $nonMember['email'];

                $classBooking = (new ClassService())->createClassBookingForClassDateAndNonMemberByUser($classDate, $nonMember['email'], $name, $request->user());
            }
        }

        if (! $classBooking instanceof ClassBooking) {
            abort(400, 'Something went wrong. Please try again later.');
        }

        return new ClassBookingResource($classBooking->withoutRelations());
    }

    public function cancelBooking(CancelBookingRequest $request, ClassBooking $booking)
    {
        if ($booking->status === ClassBookingStatus::CANCELLED_BY_COACH || $booking->status === ClassBookingStatus::CANCELLED || $booking->status === ClassBookingStatus::CANCELLED_AFTER_THRESHOLD) {
            abort(400, 'This booking is already cancelled.');
        }

        $cancelResult = (new ClassService())->cancelBookingForClassDateByUser($booking, $request->user(), true, $request->input('is_late_cancellation'));

        if ($booking->user instanceof User) {
            $attendanceRecord = (new AttendanceRecordService())->getLatestAttendanceRecordForMember($booking->user, $booking);

            if ($attendanceRecord instanceof AttendanceRecord) {
                $attendanceRecord->delete();
            }
        }

        return new ClassBookingResource($booking);
    }

    #[QueryParam('filter[location_id]', 'integer', null, false)]
    #[QueryParam('filter[class_date_between]', 'array', null, true)]
    public function getClassBookingsByPackage(GetClassBookingsByPackageRequest $request, Package $package)
    {
        $classBookings = QueryBuilder::for(ClassBooking::class)
            ->allowedFilters([
                AllowedFilter::exact('location_id', 'classes.box_facility_id'),
                AllowedFilter::scope('class_date_between', 'between'),
            ])
            ->allowedIncludes([
                'classDate',
            ])
            ->join('classes', 'classes.class_id', '=', 'class_to_dates.class_id')
            ->join(DB::raw('user_to_package as utp FORCE INDEX (PRIMARY)'), 'utp.user_to_package_id', '=', 'class_bookings.user_package_id')
            ->where('class_bookings.class_booking_status_id', '=', ClassBookingStatus::BOOKED->value)
            ->where('classes.is_session', '=', false)
            ->where('utp.package_id', '=', $package->getKey())
            ->_paginate();

        return ClassBookingResource::collection($classBookings);
    }

    public function noShow(NoShowRequest $request, ClassBooking $booking): ClassBookingResource
    {
        if ($booking->class_booking_status_id == ClassBookingStatus::NO_SHOW) {
            $booking->update([
                'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
            ]);
        } else {

            $booking->update([
                'class_booking_status_id' => ClassBookingStatus::NO_SHOW->value,
                'is_checked_in' => false,
                'checked_in_at' => null,
                'checked_out_at' => null,
            ]);

            $classDate = $booking->classDate;
            $classTime = (new ClassService())->getClassDateStartDateTime($classDate)->format('H:i').' - '.(new ClassService())->getClassDateEndDateTime($classDate)->format('H:i');

            $headCoaches = TenantUser::query()
                ->where('box_id', $booking->class->tenant->box_id)
                ->where('user_type_id', UserType::HEAD_COACH->value)
                ->where('end_date', '>', today());

            $ccArray = [];

            foreach ($headCoaches as $coach) {
                $ccArray[] = $coach->user->email;
            }

            if ($booking->user instanceof User) {
                $email = $booking->user;
                $name = $booking->user->name;
                $surname = $booking->user->surname;
            } elseif ($booking->leadMember instanceof LeadMember) {
                $email = $booking->leadMember->email_address;
                $name = $booking->leadMember->first_name;
                $surname = $booking->leadMember->last_name;
            } else {
                $email = $booking->non_member_email;
                $name = $booking->non_member_name;
                $surname = '';
            }

            (new CrmService())->createScheduledEmailForNotification(
                tenantOrLocation: $booking->class->location,
                context: 'no_show',
                recipient: $email,
                cc: $ccArray,
                data: [
                    'member_name' => $name,
                    'member_surname' => $surname,
                    'class_name' => $classDate->name(),
                    'class_time' => $classTime,
                    'class_booking_date' => $classDate->buildDateTime()->format('D, d F Y'),
                    'facility_name' => $booking->class->tenant->name,
                    'location_name' => $booking->class->location->name,
                ]
            );

            // Check if classBooking user has an attendance record and if the status is not booked in then remove attendance record.
            if ($booking->user instanceof User) {
                $attendanceRecord = (new AttendanceRecordService())->getLatestAttendanceRecordForMember($booking->user, $booking);

                if ($attendanceRecord instanceof AttendanceRecord) {
                    $attendanceRecord->delete();
                }
            }

            if ($booking->userPackage?->package?->no_show_fee > 0) {
                (new InvoiceService())->generateNoShowFeeForClassBooking($booking);
            }

        }

        return new ClassBookingResource($booking);
    }

    public function checkIn(CheckInRequest $request, ClassBooking $booking): ClassBookingResource
    {
        if ($booking->status === ClassBookingStatus::CANCELLED_BY_COACH) {
            abort(400, 'Cannot check in to cancelled class.');
        }

        $booking->update([
            'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
            'is_checked_in' => ! $booking->is_checked_in,
        ]);

        if ($booking->is_checked_in) {
            $booking->update([
                'checked_in_at' => today(),
            ]);
        } else {
            $booking->update([
                'checked_in_at' => null,
            ]);
        }

        return new ClassBookingResource($booking);
    }

    public function getClassBookingsStats(GetClassBookingStatsRequest $request): JsonResponse
    {
        $lastBookingData = null;
        $weekStartDate = date('Y-m-d', strtotime('monday this week'));
        $weekEndDate = date('Y-m-d', strtotime('sunday this week'));
        $monthStartDate = date('Y-m-d', strtotime('first day of this month'));
        $monthEndDate = date('Y-m-d', strtotime('last day of this month'));

        $box = Tenant::query()->find($request->input('filter.tenant_id'));
        $user = User::withTrashed()->find($request->input('filter.user_id'));

        // Get member last booking
        $lastBooking = (new ClassBookingsService())->getMemberLastBookingForBox($user, $box);

        if ($lastBooking instanceof ClassBooking) {
            if ($lastBooking->classDate->start_time !== null) {
                $startTime = $lastBooking->classDate->startTime();
            } else {
                $startTime = $lastBooking->class->start_time->toTimeString();
            }

            $lastBookingData = [
                'id' => $lastBooking->getKey(),
                'start_time' => $startTime,
                'date' => $lastBooking->classDate->class_date->toDateString(),
            ];
        }

        // Get bookings for this week
        $weeksBookings = (new ClassBookingsService())->getMemberBookingsForDatesForBox($user, $box, $weekStartDate, $weekEndDate, [ClassBookingStatus::BOOKED->value]);

        // Get bookings for this week
        $monthsBookings = (new ClassBookingsService())->getMemberBookingsForDatesForBox($user, $box, $monthStartDate, $monthEndDate, [ClassBookingStatus::BOOKED->value]);

        return response()->json(
            [
                'last_booking' => $lastBookingData,
                'weeks_bookings' => count($weeksBookings),
                'months_bookings' => count($monthsBookings),
            ]
        );
    }

    public function sendMessageToAthlete(MessageAthleteRequest $request, ClassBooking $booking): Response
    {
        $classDate = $booking->classDate;
        $class = $classDate->class;
        $classTime = (new ClassService())->getClassDateStartDateTime($classDate)->format('H:i').' - '.(new ClassService())->getClassDateEndDateTime($classDate)->format('H:i');

        // Send message to class booking (user, lead or non-member)
        (new ClassService())->sendMessageToClassBookingOrClassWaitingBooking($booking, $request->safe()->collect()->get('message'));

        $crm = resolve(CrmService::class);

        if ($booking->user) {
            $crm->createScheduledPushNotification(
                title: 'Message From Instructor',
                content: $request->message,
                user: $booking->user,
                tenant: $class->tenant,
                location: $class->location,
            );
        }

        $crm->createScheduledEmailForNotification(
            tenantOrLocation: $class->location,
            context: 'confirmation_member_message',
            recipient: auth()->user(),
            data: [
                'coach_name' => auth()->user()->name,
                'coach_surname' => auth()->user()->surname,
                'coach_message' => nl2br($request->message),
                'class_name' => $classDate->name(),
                'class_time' => $classTime,
                'class_booking_date' => $classDate->buildDateTime()->format('D, d F Y'),
            ]
        );

        return response()->noContent();
    }

    #[QueryParam('filter[location_id]', 'integer', null, false)]
    #[QueryParam('filter[start_date]', 'date', null, false)]
    #[QueryParam('filter[end_date]', 'date', null, false)]
    public function getMyClassBooking(GetMyClassBookingsRequest $request)
    {
        $boxFacility = $request->input('filter.location_id') ? Location::query()->findOrFail($request->input('filter.location_id')) : null;
        $startDateTime = $request->input('filter.start_date') ? new DateTime($request->input('filter.start_date')) : null;
        $endDateTime = $request->input('filter.end_date') ? new DateTime($request->input('filter.end_date')) : null;
        $ordering = $request->safe()->collect()->get('order');

        if (($startDateTime instanceof DateTime && $endDateTime instanceof DateTime) && $startDateTime > $endDateTime) {
            abort(400, 'The end date needs to be greater than the start date.');

        }

        $collection = (new ClassService())->getMyClassBookings($boxFacility, $startDateTime, $endDateTime, $ordering);

        if ($collection->isEmpty()) {
            return response()->json();
        }

        return ClassBookingResource::collection($collection->paginate());
    }
}
