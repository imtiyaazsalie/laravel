<?php

namespace App\Services;

use App\Enums\ClassBookingStatus;
use App\Enums\PackageType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Exceptions\Class\AttendanceLimitReachedException;
use App\Exceptions\Class\NoEligiblePackageException;
use App\Jobs\ProcessWaitingList;
use App\Models\AttendanceRecord;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\Classes;
use App\Models\ClassRecurringBooking;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserPackage;
use Carbon\Carbon as CarbonCarbon;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClassBookingsService
{
    /**
     * Create booking
     *
     * @throws HttpException|NoEligiblePackageException|AttendanceLimitReachedException
     */
    public function create(
        ClassDate $classDate,
        TenantUser $currentTenantUser,
        ?TenantUser $tenantUser = null,
        ?LeadMember $leadMember = null,
        ?array $nonMember = null,
    ): ClassBooking {
        /**
         * Check service input
         */
        if ($tenantUser && ($leadMember || $nonMember)) {
            throw new RuntimeException('Class booking can only be created for one type of user at a time.');
        }

        if ($leadMember && ($tenantUser || $nonMember)) {
            throw new RuntimeException('Class booking can only be created for one type of user at a time.');
        }

        if ($nonMember && ($tenantUser || $leadMember)) {
            throw new RuntimeException('Class booking can only be created for one type of user at a time.');
        }

        if ($nonMember) {
            if (! $nonMemberEmail = Arr::get($nonMember, 'email')) {
                throw new RuntimeException('Class booking for non member requires and email address.');
            }
            $nonMemberName = Arr::get($nonMember, 'name', $nonMemberEmail);
        } elseif ($leadMember) {
            $nonMemberName = $leadMember->name;
            $nonMemberEmail = $leadMember->email_address;
        } else {
            $nonMemberName = null;
            $nonMemberEmail = null;
        }

        /**
         * Ensure the class is open for booking regardless of who is booking.
         */
        $this->ensureClassOpenForBooking($classDate);

        $this->ensureAttendanceLimitNotReached($classDate);

        /**
         * When booking for a user, check they have an eligible package to make the booking
         */
        if ($tenantUser) {
            try {
                $userPackage = $this->getEligiblePackageForClassDate(
                    tenantUser: $tenantUser,
                    classDate: $classDate,
                    isCoach: ! $currentTenantUser->isMember()
                );
            } catch (HttpException) {
                throw new NoEligiblePackageException();
            }
        } else {
            $userPackage = null;
        }

        /**
         * When booking for a lead member ensure they don't already have a booking.
         */
        if ($leadMember) {
            $this->ensureUniqueLeadMemberBooking($classDate->getKey(), $leadMember->getKey());
        }

        /**
         * Create a booking
         */
        $booking = new ClassBooking([
            'class_to_date_id' => $classDate->getKey(),
            'class_id' => $classDate->class_id,
            'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
            'is_checked_in' => false,
            'created_by_id' => $currentTenantUser->user_id,

            /**
             * User bookings
             */
            'user_id' => $userPackage ? $userPackage->user_id : null,
            'user_package_id' => $userPackage ? $userPackage->getKey() : null,

            /**
             * Lead member bookings
             */
            'lead_member_id' => isset($leadMember) ? $leadMember->getKey() : null,

            /**
             * Non-member and lead member bookings
             */
            'non_member_name' => $nonMemberName,
            'non_member_email' => $nonMemberEmail,
        ]);

        /**
         * Save the booking and deduct package session.
         */
        return DB::transaction(function () use ($booking, $userPackage, $classDate) {
            if ($userPackage && $this->deductSession($userPackage, $classDate)) {
                $booking->fill(['top_up_used' => true])->save();
            }

            $booking->save();

            return $booking;
        });
    }

    /**
     * Ensure the class is open for booking.
     *
     *
     * @throws HttpException
     */
    public function ensureClassOpenForBooking(ClassDate $classDate): void
    {
        $classDate->loadMissing(['class.facility', 'class.tenant']);

        if ($classDate->bookingThreshold()->isPast()) {
            abort(Response::HTTP_BAD_REQUEST, 'Class is no longer open for booking.');
        }

        if (! $classDate->is_active) {
            abort(Response::HTTP_BAD_REQUEST, 'Class is not active.');
        }
    }

    /**
     * Ensure class attendance limit has not been reached.
     *
     * @return int // The number of spots available in the class.
     *
     * @throws AttendanceLimitReachedException
     */
    public function ensureAttendanceLimitNotReached(ClassDate $classDate): int
    {
        $spotsAvailable = $classDate->attendanceLimit() - $this->getBookingsCount($classDate);

        if ($spotsAvailable < 1) {
            throw new AttendanceLimitReachedException();
        }

        return $spotsAvailable;
    }

    public function getBookingsCount(ClassDate $classDate): int
    {
        return ClassBooking::query()
            ->whereClassToDateId($classDate->getKey())
            ->whereIn('class_booking_status_id', [ClassBookingStatus::BOOKED->value, ClassBookingStatus::NO_SHOW->value])
            ->count();
    }

    /**
     * Get user package that is eligible to book for a class date.
     *
     *
     * @throws HttpException
     */
    public function getEligiblePackageForClassDate(
        TenantUser $tenantUser,
        ClassDate $classDate,
        ?Collection $existingBookings = null,
        bool $isCoach = false
    ): UserPackage {
        /**
         * Check membership status
         */
        if ($tenantUser->user_status_id === UserStatus::SUSPENDED) {
            abort(Response::HTTP_BAD_REQUEST, 'Your account has been suspended.');
        }

        if ($tenantUser->user_status_id === UserStatus::SUSPENDED) {
            abort(Response::HTTP_BAD_REQUEST, 'Your account is in a transfer state. Please contact your studio to resolve this issue.');
        }

        if ($tenantUser->user_status_id === UserStatus::ON_HOLD) {
            abort(Response::HTTP_BAD_REQUEST, 'Your account has been placed on-hold.');
        }

        if ($tenantUser->user_status_id !== UserStatus::ACTIVE) {
            abort(Response::HTTP_BAD_REQUEST, 'Your account in not active.');
        }

        /**
         * Check existing bookings
         */
        $existingBookings = $existingBookings ?? $this->getUserBookings($tenantUser->user->getAuthIdentifier(), $classDate->class_date);

        //if existing booking for this class
        if ($existingBookings->where('class_to_date_id', $classDate->getKey())
            ->where('user_id', $tenantUser->user->user_id)
            ->first()) {
            abort(Response::HTTP_BAD_REQUEST, 'You have already booked for this class.');
        }

        // cancel if existing booking on the day and the facility only allows one booking per day
        if ($classDate->class->location->max_bookings_per_athlete_per_day === 1
            && in_array(
                $tenantUser->user->user_id,
                $existingBookings->where('class_booking_status_id', '!=', ClassBookingStatus::CHECKED_IN)
                    ->pluck('user_id')
                    ->toArray()
            )
        ) {
            abort(Response::HTTP_BAD_REQUEST, 'You have reached the maximum bookings per day at this facility.');
        }

        // cancel if the member may not book at this box facility
        if ($isCoach && $classDate->class->tenant->limit_inter_facility_bookings
            && ! in_array($classDate->class->location->getKey(), $tenantUser->user->userLocations->pluck('box_facility_id')->toArray())
        ) {
            abort(Response::HTTP_BAD_REQUEST, 'You may not book at this facility.');
        }

        /**
         * Check user package(s)
         */
        if (! $userPackage = $tenantUser->user->userPackages->filter(function ($userPackage) use ($tenantUser, $classDate) {
            return $userPackage->package
                && $userPackage->package->classes->pluck('class_id')->contains($classDate->class_id)
                && in_array($classDate->class->location->getKey(), $tenantUser->user->userLocations->pluck('box_facility_id')->toArray());
        })->first()) {
            abort(Response::HTTP_BAD_REQUEST, 'Your package is not allowed to book this class.');
        }

        if ($userPackage->end_date === null || $userPackage->end_date->lt($classDate->buildDateTime())) {
            abort(Response::HTTP_BAD_REQUEST, 'Your package will have ended by '.$classDate->buildDateTime()->format('Y-m-d'));
        }

        //if not a free class and member is making the booking
        if (! $classDate->class->isFree() && ! $isCoach) {
            // cancel if no sessions available and the package is limited
            if ($userPackage->package->isLimited() && $userPackage->sessions_available < 1) {
                abort(Response::HTTP_BAD_REQUEST, 'You do not have any sessions available on your package.');
            }

            // get member bookings for box or facility depending on box limit_inter_facility_bookings boolean
            $membersBookings = $existingBookings->where('user_id', $tenantUser->user->user_id);
            if ($classDate->class->tenant->limit_inter_facility_bookings) {
                $membersBookings = $membersBookings->where('box_facility_id', $classDate->class->location->getKey());
            }

            //ensure daily booking limit for the facility is not reached
            if ($classDate->class->location->max_bookings_per_athlete_per_day - $membersBookings->count() <= 0) {
                abort(Response::HTTP_BAD_REQUEST, 'You have reached your maximum bookings per day limit.');
            }
        }

        return $userPackage;
    }

    public function getUserBookings(array|string $userIds, ?string $date = null)
    {
        $query = ClassBooking::query()
            ->whereIn('user_id', (array) $userIds)
            ->whereIn('class_booking_status_id', [
                ClassBookingStatus::BOOKED, ClassBookingStatus::CANCELLED_AFTER_THRESHOLD, ClassBookingStatus::NO_SHOW, ClassBookingStatus::CHECKED_IN,
            ]);

        if ($date) {
            $query->whereRelation('classDate', 'class_date', '=', $date);
        }

        return $query->get();
    }

    /**
     * Ensure unique lead member booking
     *
     *
     * @throw HttpException
     */
    public function ensureUniqueLeadMemberBooking(string|int $classDateId, string|int $leadMemberId): void
    {
        if (ClassBooking::query()
            ->whereClassToDateId($classDateId)
            ->whereLeadMemberId($leadMemberId)
            ->whereIn('class_booking_status_id', [
                ClassBookingStatus::BOOKED->value, ClassBookingStatus::NO_SHOW->value,
            ])->exists()) {
            abort(Response::HTTP_BAD_REQUEST, 'Lead member already has a booking for this class');
        }
    }

    /**
     * Deduct available sessions from package if appropriate.
     *
     *
     * @return bool true if topUp was used, false if no session is deducted or no topUp is used.
     */
    public function deductSession(UserPackage $userPackage, ClassDate $classDate): bool
    {
        if ($classDate->class->isFree()) {
            return false;
        }

        if ($userPackage->package->type === PackageType::LIMITED) {
            $userPackage->decrement('sessions_available');

            return false;
        }

        if ($userPackage->sessions_available > 0 && $userPackage->getSessionsAvailable($classDate->class_date) >= 0) {
            // deduct session and set top up used if package has sessions available and
            $userPackage->decrement('sessions_available');

            return true;
        }

        return false;
    }

    public function cancel(
        ClassBooking $booking,
        TenantUser $actionedBy,
        ?bool $isLateCancellation = null, // if actionedBy by coach use this value, if provided.
        bool $deleteBooking = false
    ): ClassBooking {

        /** @var CrmService */
        $crm = resolve(CrmService::class);

        $booking->loadMissing(['userPackage', 'classDate', 'class.tenant', 'class.facility']);

        $isLateCancellation = $actionedBy->isMember() ? null : $isLateCancellation;

        $actionedByMember = $booking->user_id === $actionedBy->user_id;

        $cancellationMessage = null;

        if ($actionedBy->isMember() && ! $actionedByMember) {
            abort(Response::HTTP_BAD_REQUEST, 'This class booking does not belong to you.');
        }

        /**
         * Cancel class booking
         */
        if ($booking->classDate->cancellationThreshold()->isPast() && ($actionedByMember || $isLateCancellation)) {
            //late cancellation
            $booking->status = ClassBookingStatus::CANCELLED_AFTER_THRESHOLD->value;
            $booking->save();

            $cancellationMessage = 'PLEASE NOTE: You have cancelled the class after the class threshold and therefore it will count towards your attendance.<br /><br />';

        } else {
            //in time cancellation
            $booking->status = $actionedByMember ? ClassBookingStatus::CANCELLED->value : ClassBookingStatus::CANCELLED_BY_COACH->value;
            $booking->save();

            //refund session
            if ($booking->top_up_used || ($booking->userPackage?->package_type_id === PackageType::LIMITED && $booking->class->isNotFree())) {
                $booking->userPackage->increment('sessions_available');
            }
        }

        if ($actionedByMember) {
            $crm->createScheduledEmailForNotification(
                tenantOrLocation: $booking->classDate->class->tenant,
                context: 'coach_cancel_message',
                recipient: $booking->class->headCoach->user,
                data: [
                    'member_name' => $booking->getName(),
                    'member_surname' => $booking->getSurname(),
                    'class_name' => $booking->classDate->name(),
                    'class_time' => $booking->classDate->startTime(),
                    'class_booking_date' => $booking->classDate->buildDateTime()->format('D, d F Y'),
                    'cancel_threshold_message' => $cancellationMessage,
                    'tenant_name' => $booking->class->tenant->name,
                    'location_name' => $booking->class->location->name,
                ]
            );
        }

        $crm->createScheduledEmailForNotification(
            tenantOrLocation: $booking->classDate->class->tenant,
            context: 'cancel_class',
            recipient: $booking->routeEmailsTo(),
            data: [
                'member_name' => $booking->getName(),
                'member_surname' => $booking->getSurname(),
                'class_name' => $booking->classDate->name(),
                'class_time' => $booking->classDate->startTime(),
                'class_booking_date' => $booking->classDate->buildDateTime()->format('D, d F Y'),
                'cancel_threshold_message' => $cancellationMessage,
                'facility_name' => $booking->class->tenant->name,
                'location_name' => $booking->class->location->name,
            ]
        );

        ProcessWaitingList::dispatch($booking->class_to_date_id);

        $this->clearAttendanceRecords($booking);

        return $booking;
    }

    /**
     * Clear latest attendance record for class booking
     */
    public function clearAttendanceRecords(ClassBooking $classBooking): bool
    {
        if (! $classBooking->user_id) {
            return true;
        }

        return AttendanceRecord::query()
            ->whereUserId($classBooking->user_id)
            ->whereClassBookingId($classBooking->getKey())
            ->latest(AttendanceRecord::CREATED_AT)
            ->delete();
    }

    /**
     * Create membership for class bookings query builder.
     *
     *
     * @return Builder<TenantUser>
     */
    public function getMembershipQueryForClassBookings(ClassDate $classDate): Builder
    {
        return TenantUser::query()
            ->with([
                'user.userPackages' => function ($query) use ($classDate) {
                    $query->with('package.classes', function ($query) use ($classDate) {
                        $query->where('classes.class_id', $classDate->class_id);
                    });

                    $query->whereHas('package.classes', function ($query) use ($classDate) {
                        $query->where('classes.class_id', $classDate->class_id);
                    });

                    $query->where(function ($query) use ($classDate) {
                        $query->where('end_date', '>=', $classDate->buildDateTime()->format('Y-m-d'))
                            ->orWhereNull('end_date');
                    });
                },
                'user.userLocations' => function ($query) {
                    $query->active();
                },
            ])
            ->active()
            ->whereBoxId($classDate->class->box_id)
            ->whereUserStatusId(UserStatus::ACTIVE->value);
    }

    /**
     * Check if the user box (membership) may book a class date.
     */
    public function canMembershipBookClassDate(
        TenantUser $tenantUser,
        ClassDate $classDate,
        ?Collection $existingBookings = null,
        bool $isCoach = false
    ): bool {
        try {
            $this->getEligiblePackageForClassDate($tenantUser, $classDate, $existingBookings, $isCoach);

            return true;
        } catch (HttpException) {
            return false;
        }
    }

    /**
     * Helper to check if the class is open for booking.
     */
    public function isClassOpenForBooking(ClassDate $classDate): bool
    {
        try {
            $this->ensureClassOpenForBooking($classDate);

            return true;
        } catch (HttpException) {
            return false;
        }
    }

    public function getBookingForAthleteAndClassDate(User $athlete, ClassDate $classDate): ?object
    {
        return ClassBooking::query()
            ->where('class_bookings.class_to_date_id', '=', $classDate->getKey())
            ->where('class_bookings.user_id', '=', $athlete->getKey())
            ->where('class_bookings.class_booking_status_id', '=', ClassBookingStatus::BOOKED->value)
            ->first();
    }

    public function getSessionsUsedForAthleteForPeriod(UserPackage $userPackage, ?DateTime $start, ?DateTime $end, Tenant $box, ?Location $boxFacility = null, ?bool $sessionsPerPackage = false, bool $excludeTopUpSessionBookings = false): int
    {
        $classBookingsQuery = ClassBooking::query()
            ->join('classes', 'classes.class_id', '=', 'class_bookings.class_id')
            ->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'classes.box_facility_id')
            ->where('class_bookings.user_id', '=', $userPackage->user_id)
            ->where('box_facility.box_id', '=', $box->getKey())
            ->where('classes.is_free', '=', false)
            ->whereNotIn('class_bookings.class_booking_status_id', [ClassBookingStatus::CANCELLED->value, ClassBookingStatus::CANCELLED_BY_COACH->value])
            ->when($boxFacility, function ($query) use ($boxFacility) {
                return $query->where('box_facility.box_facility_id', '=', $boxFacility->getKey());
            })
            ->when($sessionsPerPackage, function ($query) use ($userPackage) {
                return $query->where('class_bookings.user_package_id', '=', $userPackage->getKey());
            })
            ->when($excludeTopUpSessionBookings, function ($query) {
                return $query->where('class_bookings.top_up_used', '=', false);
            });

        if ($start && $end) {
            $start->setTime(0, 0);
            $end->setTime(23, 59, 59);

            $classBookingsQuery->whereBetween('class_to_dates.class_date', [$start, $end]);
        } elseif ($start) {
            $start->setTime(0, 0);

            $classBookingsQuery->where('class_to_dates.class_date', '>=', $start);
        } elseif ($end) {
            $end->setTime(23, 59, 59);

            $classBookingsQuery->where('class_to_dates.class_date', '<=', $end);
        }

        return $classBookingsQuery->get()->count();
    }

    public function getMemberBookingsForDates(User $user, $startDate, $endDate, $statuses = null): Collection|array
    {
        return ClassBooking::query()
            ->join('class_to_dates', 'class_bookings.class_to_date_id', '=', 'class_to_dates.class_to_date_id')
            ->where('class_bookings.user_id', $user->getKey())
            ->whereBetween('class_to_dates.class_date', [$startDate, $endDate])
            ->when($statuses, function ($query) use ($statuses) {
                return $query->whereIn('class_booking_status_id', $statuses);
            })
            ->get();
    }

    public function getClassBookingsForUser(User $user, ?Location $boxFacility = null, ?DateTime $startDate = null, ?DateTime $endDate = null, ?int $classBookingStatusId = null, ?string $orderingDirection = 'ASC', ?bool $isCount = false): Collection|int|array
    {
        $startDate = $startDate instanceof DateTime ? $startDate->setTime(0, 0) : null;
        $endDate = $endDate instanceof DateTime ? $endDate->setTime(0, 0) : null;
        $classBookingStatusId = $classBookingStatusId ?? ClassBookingStatus::BOOKED;

        $query = ClassBooking::query()
            ->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->join('classes', 'classes.class_id', '=', 'class_bookings.class_id')
            ->where('class_bookings.user_id', $user->getKey())
            ->where('class_bookings.class_booking_status_id', $classBookingStatusId)
            ->orderBy('class_to_dates.class_date');

        if ($boxFacility) {
            $query->where('classes.box_facility_id', $boxFacility->getKey());
        }

        // Get class for date range if both start and end date have been set
        if ($startDate instanceof DateTime && $endDate instanceof DateTime) {
            $query->whereBetween('class_to_dates.class_date', [$startDate, $endDate]);
        }

        // Get future classes if no end date was set
        if ($startDate instanceof DateTime && ! $endDate instanceof DateTime) {
            $query->where('class_to_dates.class_date', '>=', $startDate);
        }

        // Get past classes if no start date was set
        if ($endDate instanceof DateTime && ! $startDate instanceof DateTime) {
            $query->where('class_to_dates.class_date', '<=', $endDate);
        }

        if ($isCount) {
            return $query->count('class_bookings.*');
        }

        return $query->get();
    }

    public function getBookingsForClassDate(ClassDate $classDate, array $classBookingStatusIds = []): Collection|array
    {
        if (empty($classBookingStatusIds)) {
            $classBookingStatusIds = [
                ClassBookingStatus::BOOKED->value, ClassBookingStatus::NO_SHOW->value, ClassBookingStatus::CHECKED_IN->value,
            ];
        }

        return ClassBooking::query()
            ->where('class_bookings.class_to_date_id', '=', $classDate->getKey())
            ->whereIn('class_bookings.class_booking_status_id', $classBookingStatusIds)
            ->get();
    }

    public function getClassBookingsForClassDateAndStatus(ClassDate $classDate, $classBookingStatusIds = []): Collection|array
    {
        return ClassBooking::query()
            ->where('class_bookings.class_to_date_id', '=', $classDate->getKey())
            ->whereIn('class_bookings.class_booking_status_id', $classBookingStatusIds)
            ->get();
    }

    public function getFutureClassBookingsForClassRecurringBookingsFromDate(ClassRecurringBooking $classRecurringBooking, $fromDate): Collection|array
    {
        return ClassBooking::query()
            ->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->where('class_to_dates.class_date', '>=', $fromDate)
            ->where('class_bookings.class_id', '=', $classRecurringBooking->class_id)
            ->where('class_bookings.user_id', '=', $classRecurringBooking->user_id)
            ->where('class_bookings.class_booking_status_id', '=', ClassBookingStatus::BOOKED)
            ->get();

    }

    public function getRecurringClassBookingsToCancel($date, Classes $class, User $athlete): Collection|array
    {
        return ClassBooking::query()
            ->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->where('class_to_dates.class_date', '>', $date)
            ->where('class_bookings.class_id', '=', $class->getKey())
            ->where('class_bookings.user_id', '=', $athlete->getKey())
            ->where('class_bookings.class_booking_status_id', '=', ClassBookingStatus::BOOKED->value)
            ->get();
    }

    public function getMemberLastBookingForBox(User $user, Tenant $box, ?DateTime $beforeDate = null): ?ClassBooking
    {
        return ClassBooking::query()
            ->join('classes', 'classes.class_id', '=', 'class_bookings.class_id')
            ->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->where('class_bookings.user_id', '=', $user->getKey())
            ->where('classes.box_id', '=', $box->getKey())
            ->where('class_bookings.class_booking_status_id', '=', ClassBookingStatus::BOOKED->value)
            ->when($beforeDate, function ($query) use ($beforeDate) {
                return $query->where('class_to_dates.class_date', '<', $beforeDate);
            })
            ->orderBy('class_to_dates.class_date', 'desc')
            ->first();
    }

    public function getMemberBookingsForDatesForBox(User $user, Tenant $box, $startDate, $endDate, $classBookingStatusIds = null): Collection|array
    {
        return ClassBooking::query()
            ->join('classes', 'classes.class_id', '=', 'class_bookings.class_id')
            ->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->where('class_bookings.user_id', '=', $user->getKey())
            ->where('classes.box_id', '=', $box->getKey())
            ->whereBetween('class_to_dates.class_date', [$startDate, $endDate])
            ->when($classBookingStatusIds, function ($query) use ($classBookingStatusIds) {
                return $query->whereIn('class_bookings.class_booking_status_id', $classBookingStatusIds);
            })
            ->get();
    }

    public function createBooking(Request $request, $userPackage = null): mixed
    {
        $classBooking = null;
        $classDate = ClassDate::query()->findOrFail($request->safe()->collect()->get('class_date_id'));

        $authUserTenant = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $classDate->class->tenant);
        $bookingUserTenant = (new TenantUserService())->getCurrentUserTenantForTenant($request->safe()->collect()->get('user_id'), $classDate->class->tenant);

        if ($request->has('user_id') && ! $bookingUserTenant instanceof TenantUser) {
            TenantUser::query()->create(
                [
                    'user_id' => $request->input('user_id'),
                    'box_id' => $classDate->class->tenant_id,
                    'effective_date' => today(),
                    'end_date' => today()->addYear(),
                    'user_type_id' => UserType::GYM_MEMBER->value,
                    'user_status_id' => UserStatus::ACTIVE->value,
                    'activated_on' => today(),
                ]
            );
        } else {
            if (! $authUserTenant instanceof TenantUser) {
                abort(400, 'User could not be found for class');
            }
        }

        if ($authUserTenant->isMember()) {
            $now = Carbon::now($bookingUserTenant->tenant->timezone->zone);
            // Box booking threshold check
            $bookingThresholdDate = $now->clone();
            $bookingThresholdDate->modify('+'.$bookingUserTenant->tenant->booking_threshold.' days');
            $bookingThresholdDate->modify('-1 hour');

            $startDateTime = (new ClassService())->getClassDateStartDateTime($classDate);

            if ($startDateTime > $bookingThresholdDate) {
                abort(400, 'You cannot book this far in advance');
            }

            $classBookingErrorMessageOrUserPackage = (new ClassService())->canAthleteBookForClassDate($classDate, $bookingUserTenant->user, false, true, $userPackage);

            if (is_string($classBookingErrorMessageOrUserPackage) && ! $classBookingErrorMessageOrUserPackage instanceof UserPackage) {
                abort(400, $classBookingErrorMessageOrUserPackage);
            }

            // Create class booking for user
            $classBooking = (new ClassService())->createClassBookingForClassDateAndAthleteByUser(
                $classDate,
                $bookingUserTenant->user,
                $authUserTenant->user,
                false,
                true,
                $userPackage
            );
        } else {
            // Coach booking a member, staff, lead or non-member into a class
            $leadId = $request->get('lead_id');
            $nonMember = $request->get('non_member');

            if ($bookingUserTenant instanceof TenantUser) {
                $user = $bookingUserTenant->user;

                if ($bookingUserTenant->user_type_id === UserType::GYM_MEMBER->value) {
                    $classBookingErrorMessageOrUserPackage = (new ClassService())->canCoachBookAthleteForClassDate($classDate, $user, true);

                    if (is_string($classBookingErrorMessageOrUserPackage) && ! $classBookingErrorMessageOrUserPackage instanceof UserPackage) {
                        abort(400, $classBookingErrorMessageOrUserPackage);
                    }

                    // Get sessions remaining
                    $usersCurrentBoxFacility = (new TenantUserService())->getLocationUserByTenant($user, $bookingUserTenant->tenant)->location;
                    $sessionsRemaining = $usersCurrentBoxFacility instanceof Location ? (new ClassService())->getSessionsRemainingForUserPackageForDate($classBookingErrorMessageOrUserPackage, $usersCurrentBoxFacility, $classDate->class_date) : null;

                    if (($classBookingErrorMessageOrUserPackage->package->limit === 0 && $sessionsRemaining <= 0) || $sessionsRemaining <= 0) {
                        $hasSessionsRemaining = false;
                    } else {
                        $hasSessionsRemaining = true;
                    }

                    if (! $hasSessionsRemaining && ! (new AccessPrivilegeService())->hasAccessToResource($authUserTenant, 'class_booking_overbook_member')) {
                        abort(Response::HTTP_UNAUTHORIZED, 'Access denied.');
                    }

                    $classBooking = (new ClassService())->createClassBookingForClassDateAndAthleteByUser($classDate, $user, $authUserTenant->user);
                } else {
                    if ((new ClassBookingsService())->getBookingForAthleteAndClassDate($user, $classDate)) {
                        abort(400, 'Staff member already has a booking for this class');
                    }

                    $classBooking = (new ClassService())->createClassBookingForClassDateAndCoachByUser($classDate, $user, $authUserTenant->user);
                }
            } elseif ($leadId) {
                $leadMember = LeadMember::query()->findOrFail($leadId);

                $leadMember = ClassBooking::query()
                    ->where('lead_member_id', '=', $leadMember->getKey())
                    ->where('class_to_date_id', '=', $classDate->getKey())
                    ->firstOrFail()->leadMember;

                $classBooking = (new ClassService())->createClassBookingForClassDateAndLeadByUser($classDate, $leadMember, $authUserTenant->user);
            } elseif ($nonMember) {
                // Skip if email is not set for user
                if (empty($nonMember['email'])) {
                    abort(400, 'Email is a required field.');
                }

                $name = $nonMember['name'] ?? $nonMember['email'];

                $classBooking = (new ClassService())->createClassBookingForClassDateAndNonMemberByUser($classDate, $nonMember['email'], $name, $authUserTenant->user);
            }
        }

        if (! $classBooking instanceof ClassBooking) {
            abort(400, 'Something went wrong. Please try again later.');
        }

        return $classBooking;
    }

    public function getBookingForLeadMemberAndClassDate(LeadMember $leadMember, ClassDate $classDate): object
    {
        return ClassBooking::query()
            ->where('class_bookings.class_to_date_id', '=', $classDate->getKey())
            ->where('class_bookings.lead_member_id', '=', $leadMember->getKey())
            ->where(function ($query) {
                $query->where('class_bookings.class_booking_status_id', '=', ClassBookingStatus::BOOKED->value)
                    ->orWhere('class_bookings.class_booking_status_id', '=', ClassBookingStatus::NO_SHOW->value);
            })
            ->first();
    }

    public function bookingThreshold(ClassDate $classDate): bool
    {
        $isPastEventDate = Carbon::now($classDate->class->tenant->timezone->zone)->isAfter(Carbon::parse(strtotime($classDate->startTime()))->setDateFrom($classDate->class_date));
        $bookingThresholdDate = Carbon::parse(strtotime($classDate->startTime()))->setDateFrom($classDate->class_date);
        $bookingThresholdTime = $bookingThresholdDate->subMinutes($classDate->class->booking_threshold)->format('H:i:s');

        return $isPastEventDate ||
            Carbon::now($classDate->class->tenant->timezone->zone)->isAfter($bookingThresholdDate) ||
            (Carbon::now($classDate->class->tenant->timezone->zone)->format('Y-m-d') === $bookingThresholdDate->format('Y-m-d') && Carbon::now($classDate->class->tenant->timezone->zone)->format('H:i:s') > $bookingThresholdTime);
    }

    public function getClassBookingsCountForBoxByYear(int $tenantId, int $year, ?int $locationId = null, ?bool $isMonthlyBreakdown = false)
    {
        $date = new DateTime("$year-01-01");

        $qb = ClassBooking::query()->from('class_bookings', 'cb');

        if ($isMonthlyBreakdown) {
            $select = '
                MONTH(cd.class_date) AS classDateMonth,
                SUM(CASE
                       WHEN cb.class_booking_status_id = 1 THEN 1
                       ELSE 0
                   END) AS bookings,
               SUM(CASE
                       WHEN (cb.class_booking_status_id = 1 AND cb.is_checked_in = 1) THEN 1
                       ELSE 0
                   END) AS checkIns,
               SUM(CASE
                       WHEN cb.class_booking_status_id IN (2, 3, 4) THEN 1
                       ELSE 0
                   END) AS allCancellations,
               SUM(CASE
                     WHEN cb.class_booking_status_id = 3 THEN 1
                     ELSE 0
                   END) AS lateCancellations,
               SUM(CASE
                       WHEN cb.class_booking_status_id = 5 THEN 1
                       ELSE 0
                   END) AS noShows
            ';
        } else {
            $select = '
               SUM(CASE
                    WHEN cb.class_booking_status_id = 1 THEN 1
                     ELSE 0
                   END) AS bookings,
               SUM(CASE
                     WHEN (cb.class_booking_status_id = 1 AND cb.is_checked_in = 1) THEN 1
                     ELSE 0
                   END) AS checkIns,
               SUM(CASE
                     WHEN cb.class_booking_status_id IN (2,3,4) THEN 1
                     ELSE 0
                   END) AS allCancellations,
               SUM(CASE
                     WHEN cb.class_booking_status_id = 3 THEN 1
                     ELSE 0
                   END) AS lateCancellations,
               SUM(CASE
                     WHEN cb.class_booking_status_id = 5 THEN 1
                     ELSE 0
                   END) AS noShows
            ';
        }

        $qb->selectRaw($select)
            ->leftJoin('class_to_dates as cd', 'cd.class_to_date_id', '=', 'cb.class_to_date_id')
            ->leftJoin('classes as c', 'c.class_id', '=', 'cd.class_id')
            ->where(function ($query) use ($date) {
                $query->where('cd.class_date', '>=', date('Y-m-d', $date->getTimestamp()))
                    ->where('cd.class_date', '<=', date('Y-m-d', strtotime('12/31', $date->getTimestamp())));
            })
            ->where('c.box_id', '=', $tenantId);

        if ($locationId) {
            $qb->where('c.box_facility_id', '=', $locationId);
        }

        if ($isMonthlyBreakdown) {
            $qb->groupBy('classDateMonth')->orderBy('classDateMonth');
        }

        return $isMonthlyBreakdown ? $qb->get() : $qb->get()->first()->withoutRelations();
    }

    public function getClassBookingsForPackage(Tenant $box, Package $package, Carbon $startDate, Carbon $endDate, ?Location $boxFacility = null, ?bool $isCount = false): Collection|array|int
    {
        $qb = ClassBooking::query()
            ->from('class_bookings', 'cb')
            ->join('class_to_dates as ctd', 'cb.class_to_date_id', '=', 'ctd.class_to_date_id')
            ->join('classes as c', 'c.class_id', '=', 'ctd.class_id')
            ->join('user_to_package as utp', 'cb.user_package_id', '=', 'utp.user_to_package_id')
            ->where('c.box_id', '=', $box->getKey())
            ->where('c.is_session', '=', false)
            ->where('utp.package_id', '=', $package->getKey())
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('ctd.class_date', '>=', $startDate->toDateString())
                    ->where('ctd.class_date', '<=', $endDate->toDateString());
            })
            ->whereNotNull('cb.user_package_id')
            ->where('cb.class_booking_status_id', '=', 1);

        if ($boxFacility instanceof Location) {
            $qb->where('c.box_facility_id', '=', $boxFacility->getKey());
        }

        if ($isCount) {
            return $qb->count();
        }

        return $qb->get();
    }

    public function getClassBookingsForClassDate(ClassDate $classDate, ?User $user = null): Collection|array
    {
        return ClassBooking::query()
            ->join('class_to_dates', 'class_bookings.class_to_date_id', 'class_to_dates.class_to_date_id')
            ->where('class_to_dates.class_date', '=', $classDate->class_date)
            ->whereIn('class_bookings.class_booking_status_id', [ClassBookingStatus::BOOKED->value, ClassBookingStatus::NO_SHOW->value, ClassBookingStatus::CHECKED_IN->value])
            ->when($user, function ($q) use ($user) {
                return $q->where('class_bookings.user_id', $user->getKey());
            })
            ->get();
    }

    public function getClassBookingsForBoxFacilityAndDate(Location $boxFacility, DateTime $date): Collection|array
    {
        return ClassBooking::query()
            ->forceIndex('class_bookings_class_to_date_id_class_booking_status_id_index')
            ->join('classes', 'class_bookings.class_id', '=', 'classes.class_id')
            ->join('class_to_dates', 'class_bookings.class_to_date_id', '=', 'class_to_dates.class_to_date_id')
            ->where('classes.box_facility_id', $boxFacility->getKey())
            ->whereIn('class_bookings.class_booking_status_id', [ClassBookingStatus::BOOKED, ClassBookingStatus::NO_SHOW, ClassBookingStatus::CHECKED_IN])
            ->where('class_to_dates.class_date', $date->format('Y-m-d'))
            ->get();
    }

    public function getClassBookingsForActiveUsers(Tenant $tenant, CarbonCarbon $startDate, CarbonCarbon $endDate, ?Location $location = null)
    {
        return ClassBooking::query()
            ->withoutGlobalScope('userTenant')
            ->join('class_to_dates', 'class_bookings.class_to_date_id', '=', 'class_to_dates.class_to_date_id')
            ->join('classes', 'class_to_dates.class_id', '=', 'classes.class_id')
            ->join('users', 'class_bookings.user_id', '=', 'users.user_id')
            ->join('user_to_box', 'users.user_id', '=', 'user_to_box.user_id')
            ->whereIn('class_bookings.class_booking_status_id', [1, 6])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('class_to_dates.class_date', '>=', $startDate->toDateString())
                    ->where('class_to_dates.class_date', '<=', $endDate->toDateString());
            })
            ->where(function ($query) {
                $query->where('user_to_box.user_status_id', UserStatus::ACTIVE)
                    ->where('user_to_box.user_type_id', UserType::GYM_MEMBER)
                    ->where('user_to_box.deleted', false)
                    ->where('user_to_box.end_date', '>=', today()->toDateTime());
            })
            ->where('classes.box_id', '=', $tenant->getKey())
            ->when($location, function ($query) use ($location) {
                return $query->where('classes.box_facility_id', $location->getKey());
            })
            ->get();
    }
}
