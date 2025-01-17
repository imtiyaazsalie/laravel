<?php

namespace App\Services;

use App\Enums\ClassBookingStatus;
use App\Enums\ClassBookingWaitingStatus;
use App\Enums\CoachType;
use App\Enums\MandateStatus;
use App\Enums\MandateType;
use App\Enums\PackageType;
use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Models\ClassBooking;
use App\Models\ClassBookingWaitingList;
use App\Models\ClassCoach;
use App\Models\ClassDate;
use App\Models\Classes;
use App\Models\ClassPackage;
use App\Models\ClassRecurringBooking;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\Mandate;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Timezone;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\CRM\DiscoveryNotificationsService;
use App\Services\PaymentGateways\GoCardlessService;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ClassService
{
    protected bool $sendEmails = true;

    protected int $recurringBookingsGenerated = 0;

    public function disableSendEmails(): ClassService
    {
        $this->sendEmails = false;

        return $this;
    }

    public function createClassBookingsForWaitingListWhenLimitChanges(Classes $class, ClassDate $classDate, $limitDifference): void
    {
        $waitingUserBookings = (new ClassBookingsWaitingService())->getWaitingForClassAndDate($class, $classDate);

        if (count($waitingUserBookings) <= 0) {
            return;
        }

        for ($i = 0; $i < $limitDifference; $i++) {
            if (! isset($waitingUserBookings[$i]) || ! $waitingUserBookings[$i] instanceof ClassBookingWaitingList) {
                continue;
            }

            // Check who created the waiting list booking
            if ($waitingUserBookings[$i]->created_by_id !== $waitingUserBookings[$i]->user_id) {
                $classBookingForWaitingListUser = $this->createClassBookingForClassDateAndAthleteByUser($classDate, $waitingUserBookings[$i]->user, $waitingUserBookings[$i]->createdBy);
            } else {
                $classBookingForWaitingListUser = $this->createClassBookingForClassDateAndAthleteByUser($classDate, $waitingUserBookings[$i]->user, $waitingUserBookings[$i]->user, null, true);
            }

            if ($classBookingForWaitingListUser instanceof ClassBooking) {
                // Update waiting list entry
                $waitingUserBookings[$i]->update(['status' => ClassBookingWaitingStatus::BOOKED]);
            }
        }
    }

    public function createClassBookingForClassDateAndAthleteByUser(ClassDate $classDate, User $athlete, User $user, $booking = null, ?bool $bypassClassFullCheck = false, ?UserPackage $userPackage = null)
    {
        if ($user->getKey() !== $athlete->getKey()) {
            if (! ($userPackage = $this->canCoachBookAthleteForClassDate($classDate, $athlete))) {
                return false;
            }
        } else {
            if (! ($userPackage = $this->canAthleteBookForClassDate($classDate, $athlete, $bypassClassFullCheck, false, $userPackage))) {
                return false;
            }
        }

        // is this user on waiting list for this class?
        $classBookingWaiting = (new ClassBookingsWaitingService())->getWaitingForClassAndDateAndUser($classDate->class, $classDate, $athlete);

        if ($classBookingWaiting instanceof ClassBookingWaitingList) {
            $this->cancelBookingWaitingForClassDateByUser($classBookingWaiting, $user);
        }

        $classBooking = ClassBooking::query()->create([
            'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
            'class_id' => $classDate->class_id,
            'class_to_date_id' => $classDate->getKey(),
            'is_checked_in' => false,
            'user_id' => $athlete->getKey(),
            'created_by_id' => $user->getKey(),
            'user_package_id' => $userPackage->getKey(),
        ]);

        if ($booking instanceof ClassRecurringBooking) {
            $classBooking->update([
                'class_recurring_booking_id' => $booking->getKey(),
            ]);
        }

        $sessionsRemaining = $this->getSessionsRemainingForUserPackageForDate($userPackage, $classDate->class->location, $classDate->class_date, true);

        // not a free class and on a limited package OR has top-up sessions and no package sessions remaining, deduct session
        if (! $classDate->class->is_free && $userPackage->package->hasLimitedSessions()) {
            $userPackage->update(['sessions_available' => $userPackage->sessions_available - 1]);
        } elseif (! $classDate->class->is_free && ! $userPackage->package->hasLimitedSessions() && $sessionsRemaining <= 0 && $userPackage->sessions_available > 0) {
            $userPackage->update(['sessions_available' => $userPackage->sessions_available - 1]);
            $classBooking->update(['top_up_used' => true]);
        }

        if ($this->sendEmails) {
            // Send notification that member was on waiting list and has been auto booked into class
            if ($classBookingWaiting instanceof ClassBookingWaitingList) {
                $this->scheduleBookingFromWaitingListConfirmationEmail($classBooking);
            } else {
                $this->scheduleBookingConfirmationEmail($classBooking);
            }
        }

        return $classBooking;
    }

    public function canCoachBookAthleteForClassDate(ClassDate $classDate, User $athlete, ?bool $isReturnErrorMessage = null, ?UserPackage $userPackage = null)
    {
        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($athlete, $classDate->class->tenant->getKey());

        if (! $userBoxMembership) {
            return $isReturnErrorMessage ? 'User does not have an active membership with this facility' : false;
        }

        // does user already have a booking for this class date?
        if ((new ClassBookingsService())->getBookingForAthleteAndClassDate($athlete, $classDate)) {
            return $isReturnErrorMessage ? "{$athlete->name} is already booked into this class" : false;
        }

        // is class and class date active?
        if (! $classDate->is_active) {
            return $isReturnErrorMessage ? 'Class date is not active' : false;
        }

        // user status ok?
        if ($userBoxMembership->user_status_id !== UserStatus::ACTIVE) {
            return $isReturnErrorMessage ? "{$athlete->name} account is not active" : false;
        }

        $userPackageErrors = 'Package(s) is not allowed to book for this class';

        $box = $classDate->class->tenant;
        $userPackageUsedForBooking = null;
        $userPackagesAllowedToBookForClass = [];

        if ($userPackage instanceof UserPackage) {
            $userPackages = [$userPackage];
        } else {
            $userPackages = (new UserPackageService())->getActiveUserPackagesForTenant($athlete, $box);
        }

        foreach ($userPackages as $userPackage) {
            if ($this->isPackageAllowedForClass($userPackage->package, $classDate->class) === false) {
                continue;
            }

            $userPackagesAllowedToBookForClass[] = $userPackage;
        }

        foreach ($userPackagesAllowedToBookForClass as $userPackageAllowedToBookForClass) {
            // Get sessions count for user package
            $locationUser = (new TenantUserService())->getLocationUserByTenant($athlete, $userBoxMembership->tenant);

            if ($this->getSessionsRemainingForUserPackage($userPackageAllowedToBookForClass, $locationUser?->location) <= 0) {
                continue;
            }

            $userPackageUsedForBooking = $userPackageAllowedToBookForClass;
            break;
        }

        if (! $userPackageUsedForBooking) {
            $userPackageUsedForBooking = $userPackagesAllowedToBookForClass[0] ?? null;
        }

        if ($userPackageUsedForBooking) {
            return $userPackageUsedForBooking;
        } else {
            return $isReturnErrorMessage ? $userPackageErrors : false;
        }
    }

    public function isPackageAllowedForClass(Package $package, Classes $class): bool
    {
        $result = ClassPackage::query()
            ->where('class_id', $class->getKey())
            ->where('package_id', $package->getKey())
            ->where('is_active', true)
            ->count();

        return $result > 0;
    }

    public function removeClassRecurringBookingsAndClassBookingsForUser(TenantUser $tenantUser): void
    {
        $now = now();

        $classRecurringBookings = ClassRecurringBooking::query()
            ->select('class_recurring_bookings.*')
            ->join('classes', 'class_recurring_bookings.class_id', '=', 'classes.class_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'classes.box_facility_id')
            ->where('class_recurring_bookings.user_id', $tenantUser->user_id)
            ->where('class_recurring_bookings.active', true)
            ->where('box_facility.box_id', $tenantUser->tenant_id)
            ->get();

        /** @var ClassRecurringBooking $classRecurringBooking */
        foreach ($classRecurringBookings as $classRecurringBooking) {

            $periodFromWhichToStartDeactivating = $now->copy()->addMinutes($classRecurringBooking->class->cancellation_threshhold);

            // These bookings will not be canceled but deleted
            $classBookings = (new ClassBookingsService())->getRecurringClassBookingsToCancel(
                $periodFromWhichToStartDeactivating, $classRecurringBooking->class, $classRecurringBooking->user
            );

            /** @var ClassBooking $classBooking */
            foreach ($classBookings as $classBooking) {
                $this->deleteBookingForClassDateByUser(
                    $classBooking, auth()->user() ?: $classBooking->user
                );
            }

            $classRecurringBooking->update([
                'active' => false,
                'dt_deactivate' => now(),
            ]);
        }
    }

    public function cancelFutureRecurringBookingsAfterUserScheduledDeactivation(TenantUser $tenantUser): void
    {
        $classRecurringBookings = ClassRecurringBooking::query()
            ->select('class_recurring_bookings.*')
            ->join('classes', 'class_recurring_bookings.class_id', '=', 'classes.class_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'classes.box_facility_id')
            ->where('class_recurring_bookings.user_id', $tenantUser->user_id)
            ->where('class_recurring_bookings.active', true)
            ->where('box_facility.box_id', $tenantUser->tenant_id)
            ->get();

        /** @var ClassRecurringBooking $classRecurringBooking */
        foreach ($classRecurringBookings as $classRecurringBooking) {
            $classRecurringBooking->update([
                'active' => false,
                'dt_deactivate' => now(),
            ]);
        }
    }

    public function cancelFutureBookingsForUser(TenantUser $tenantUser, ?bool $isDeleteClassBookings = false): void
    {
        $futureClassBookings = ClassBooking::query()
            ->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->join('classes', 'classes.class_id', '=', 'class_bookings.class_id')
            ->join('box_facility', 'box_facility.box_facility_id', 'classes.box_facility_id')
            ->where('class_bookings.user_id', $tenantUser->user_id)
            ->where('box_facility.box_id', $tenantUser->tenant_id)
            ->where('class_bookings.class_booking_status_id', ClassBookingStatus::BOOKED->value)
            ->where('class_to_dates.class_date', '>=', now()->toDateString())
            ->where('class_bookings.is_checked_in', '!=', 1)
            ->orderBy('class_to_dates.class_date')
            ->get();

        foreach ($futureClassBookings as $classBooking) {
            $this->cancelBookingForClassDateByUser($classBooking, $tenantUser->user, false);

            if ($isDeleteClassBookings) {
                // remove related attendance records before deleting to handle foreign key constraint
                $classBooking->attendanceRecords()->delete();

                $classBooking->delete();
            }
        }
    }

    public function getSessionsRemainingForUserPackage(UserPackage $userPackage, Location $location): int
    {
        $package = $userPackage->package;

        if ($package->hasLimitedSessions()) {
            return $userPackage->sessions_available ?? 0;
        }

        if (($package->type === PackageType::WEEKLY || $package->type === PackageType::MONTHLY) && $package->limit === 0) {
            return 0; // unlimited package
        }

        $date = new DateTime('now', new DateTimeZone($location->timezone->zone ?? $location->tenant->timezone?->zone));

        $today = new DateTime();
        $today->setTime(23, 23, 59);

        if ($package->type === PackageType::WEEKLY) {
            $monday = clone $date;

            if ($date->format('l') === 'Monday') {
                $sunday = clone $date;
                $sunday->add(new DateInterval('P6D'));
            } elseif ($date->format('l') === 'Tuesday') {
                $monday->sub(new DateInterval('P1D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P5D'));
            } elseif ($date->format('l') === 'Wednesday') {
                $monday->sub(new DateInterval('P2D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P4D'));
            } elseif ($date->format('l') === 'Thursday') {
                $monday->sub(new DateInterval('P3D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P3D'));
            } elseif ($date->format('l') === 'Friday') {
                $monday->sub(new DateInterval('P4D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P2D'));
            } elseif ($date->format('l') === 'Saturday') {
                $monday->sub(new DateInterval('P5D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P1D'));
            } else {
                $monday->sub(new DateInterval('P6D'));
                $sunday = clone $date;
            }

            $totalSessionsAvailable = $package->package_limit;

            if ($userPackage->sessions_available > 0) {
                $totalSessionsAvailable += $userPackage->sessions_available;
            }

            if ($location->tenant->limit_inter_facility_bookings) {
                $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $monday, $sunday, $location->tenant, $location, true, true);
            } else {
                $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $monday, $sunday, $location->tenant, null, true, true);
            }

            return $totalSessionsAvailable - $bookedForPeriod;
        } elseif ($package->type === PackageType::MONTHLY) {
            $firstOfThisMonth = new DateTime();
            $firstOfThisMonth->setTimestamp(strtotime('first day of this month', $date->getTimestamp()));

            $lastOfThisMonth = new DateTime();
            $lastOfThisMonth->setTimestamp(strtotime('last day of this month + 11 hours 59 minutes 59 seconds', $date->getTimestamp()));

            $totalSessionsAvailable = $package->package_limit;

            if ($userPackage->sessions_available > 0) {
                $totalSessionsAvailable += $userPackage->sessions_available;
            }

            if ($location->tenant->limit_inter_facility_bookings) {
                $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $firstOfThisMonth, $lastOfThisMonth, $location->tenant, $location, true, true);
            } else {
                $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $firstOfThisMonth, $lastOfThisMonth, $location->tenant, null, true, true);
            }

            return $totalSessionsAvailable - $bookedForPeriod;
        } else {
            return 0;
        }
    }

    public function getSessionsRemainingForUserPackageForDisplay(UserPackage $userPackage, Location $location)
    {
        $package = $userPackage->package;

        if ($package->hasLimitedSessions()) {
            return $userPackage->sessions_available ?? 0;
        }

        if ($package->type === PackageType::MONTHLY && $package->limit === 0) {
            return '∞ this month';
        }

        if ($package->type === PackageType::WEEKLY && $package->limit === 0) {
            return '∞ this week';
        }

        $date = new DateTime('now', new DateTimeZone($location->timezone->zone ?? $location->tenant->timezone?->zone));

        $today = new DateTime();
        $today->setTime(23, 23, 59);

        if ($package->package_limit_type_id === PackageType::WEEKLY) {
            $monday = clone $date;

            if ($date->format('l') === 'Monday') {
                $sunday = clone $date;
                $sunday->add(new DateInterval('P6D'));
            } elseif ($date->format('l') === 'Tuesday') {
                $monday->sub(new DateInterval('P1D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P5D'));
            } elseif ($date->format('l') === 'Wednesday') {
                $monday->sub(new DateInterval('P2D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P4D'));
            } elseif ($date->format('l') === 'Thursday') {
                $monday->sub(new DateInterval('P3D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P3D'));
            } elseif ($date->format('l') === 'Friday') {
                $monday->sub(new DateInterval('P4D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P2D'));
            } elseif ($date->format('l') === 'Saturday') {
                $monday->sub(new DateInterval('P5D'));
                $sunday = clone $date;
                $sunday->add(new DateInterval('P1D'));
            } else {
                $monday->sub(new DateInterval('P6D'));
                $sunday = clone $date;
            }

            $totalSessionsAvailable = $package->package_limit;

            if ($userPackage->sessions_available > 0) {
                $totalSessionsAvailable += $userPackage->sessions_available;
            }

            if ($location->tenant->limit_inter_facility_bookings) {
                $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $monday, $sunday, $location->tenant, $location, true, true);
            } else {
                $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $monday, $sunday, $location->tenant, null, true, true);
            }

            $sessionsRemaining = $totalSessionsAvailable - $bookedForPeriod;

            return $sessionsRemaining.' this week';
        } elseif ($package->package_limit_type_id === PackageType::MONTHLY) {
            $firstOfThisMonth = new DateTime();
            $firstOfThisMonth->setTimestamp(strtotime('first day of this month', $date->getTimestamp()));

            $lastOfThisMonth = new DateTime();
            $lastOfThisMonth->setTimestamp(strtotime('last day of this month + 11 hours 59 minutes 59 seconds', $date->getTimestamp()));

            $totalSessionsAvailable = $package->package_limit;

            if ($userPackage->sessions_available > 0) {
                $totalSessionsAvailable += $userPackage->sessions_available;
            }

            if ($location->tenant->limit_inter_facility_bookings) {
                $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $firstOfThisMonth, $lastOfThisMonth, $location->tenant, $location, true, true);
            } else {
                $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $firstOfThisMonth, $lastOfThisMonth, $location->tenant, null, true, true);
            }

            $sessionsRemaining = $totalSessionsAvailable - $bookedForPeriod;

            return $sessionsRemaining.' this month';
        } else {
            return '∞ this month';
        }
    }

    public function canCoachBookAthleteOntoWaitingListForClassDate(ClassDate $classDate, User $athlete, ?bool $isReturnErrorMessage = false): bool|string
    {
        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($athlete, $classDate->class->tenant);

        if (! $userBoxMembership instanceof TenantUser) {
            return $isReturnErrorMessage ? 'User does not have an active membership with this facility' : false;
        }

        // does user already have a booking waiting for this class date?
        if ((new ClassBookingsWaitingService())->getBookingWaitingForAthleteAndClassDate($athlete, $classDate)) {
            return $isReturnErrorMessage ? "{$athlete->name} is already on the waiting list for this class'" : false;
        }

        // is class and class date active?
        if (! $classDate->is_active) {
            return $isReturnErrorMessage ? 'Class is not active' : false;
        }

        // user status ok?
        if ($userBoxMembership->status !== UserStatus::ACTIVE) {
            return $isReturnErrorMessage ? "{$athlete->name} account is not active" : false;
        }

        $userPackageErrors = '';

        $userPackages = (new UserPackageService())->getActiveUserPackagesForTenant($athlete, $classDate->class->tenant, $classDate->date);

        if ($userPackages->isEmpty()) {
            return $isReturnErrorMessage ? 'User has no active packages.' : false;
        }

        /** @var UserPackage $userPackage */
        foreach ($userPackages as $userPackage) {
            // is package allowed for this class?
            if ($this->isPackageAllowedForClass($userPackage->package, $classDate->class) === false) {
                $userPackageErrors .= $isReturnErrorMessage ? "{$athlete->name}’s package ({$userPackage->package->name}) is not allowed to book this class" : false;

                continue;
            }

            //TODO: Does package have sessions left OR may coach overbook with "class_booking_overbook_member".

            return true;
        }

        return $isReturnErrorMessage ? $userPackageErrors : false;
    }

    public function canAthleteBookForClassDate(ClassDate $classDate, User $athlete, ?bool $bypassClassFullCheck = false, ?bool $isReturnErrorMessage = false, ?UserPackage $userPackage = null)
    {
        $location = $classDate->class->location;
        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($athlete, $classDate->class->tenant);

        if (! $userBoxMembership) {
            return $isReturnErrorMessage ? 'You don\'t have an active membership with this facility' : false;
        }

        if ($userBoxMembership->user_status_id === UserStatus::SUSPENDED) {
            return $isReturnErrorMessage ? 'Your account has been suspended' : false;
        }

        if ($userBoxMembership->user_status_id === UserStatus::READY_FOR_TRANSFER) {
            return $isReturnErrorMessage ? 'Your account is in a transfer state. Please contact your studio to resolve this issue.' : false;
        }

        if ($userBoxMembership->user_status_id === UserStatus::ON_HOLD) {
            return $isReturnErrorMessage ? 'Your account has been placed on-hold' : false;
        }

        // user status ok?
        if ($userBoxMembership->user_status_id !== UserStatus::ACTIVE) {
            return $isReturnErrorMessage ? 'Your account is not active' : false;
        }

        if ($userBoxMembership->user_debit_status_id === UserDebitStatus::DEBIT_ORDER && in_array($location->payment_gateway_id, [PaymentGateway::SEPA->value, PaymentGateway::GO_CARDLESS->value])) {
            if ($location->payment_gateway_id === PaymentGateway::SEPA->value) {
                $latestMandate = (new MandateService())->getLatestMandate($athlete, $location->tenant_id, MandateType::SEPA);

                if (! $latestMandate instanceof Mandate || $latestMandate->status !== MandateStatus::ACTIVE) {
                    $link = str(config('octiv.web_app_url'))
                        ->append('/sign/mandate?userTenantId=')
                        ->append($userBoxMembership->getKey())
                        ->toString();

                    return $isReturnErrorMessage ? "You do not have an active mandate. Please sign one: $link" : false;
                }
            }

            if ($location->payment_gateway_id === PaymentGateway::GO_CARDLESS->value) {
                $isOnboarded = (new GoCardlessService())->isUserOnBoard($athlete, $location);

                if (! $isOnboarded) {
                    try {
                        $link = (new GoCardlessService())->beginUserOnBoardingFlow($athlete, $location);

                        return $isReturnErrorMessage ? "You do not have an active mandate. Please sign one: $link" : false;
                    } catch (\Exception $e) {
                        return $isReturnErrorMessage ? $e->getMessage() : false;
                    }
                }
            }
        }

        // does user already have a booking for this class date?
        if ((new ClassBookingsService())->getBookingForAthleteAndClassDate($athlete, $classDate)) {
            return $isReturnErrorMessage ? 'You have already booked this class' : false;
        }

        $box = $userBoxMembership->tenant;

        if ($classDate->class->tenant->limit_inter_facility_bookings && (new TenantUserService())->getLocationUserByTenant($athlete, $box)?->location_id !== $classDate->class->box_facility_id) {
            return $isReturnErrorMessage ? 'You are only allowed to make bookings at '.(new TenantUserService())->getLocationUserByTenant($athlete, $box)?->location->name : false;
        }

        if ($this->isClassDateFull($classDate) && ! $bypassClassFullCheck) {
            return $isReturnErrorMessage ? 'Class is fully booked' : false;
        }

        // is class and class date active?
        if (! $classDate->is_active) {
            return $isReturnErrorMessage ? 'Class is not active' : false;
        }

        $now = new DateTime('now', new DateTimeZone($box->timezone->zone));

        // is class in future?
        if ($this->getClassDateEndDateTime($classDate) < $now) {
            return $isReturnErrorMessage ? 'This class is in the past' : false;
        }

        if ($userPackage instanceof UserPackage) {
            if ($this->isPackageAllowedForClass($userPackage->package, $classDate->class) === false) {
                return $isReturnErrorMessage ? "Your package ({$userPackage->package->name}) is not allowed to book this class" : false;
            }

            // check if the class date is > the user's package end date
            if ($userPackage->end_date && $classDate->class_date > $userPackage->end_date) {
                return $isReturnErrorMessage ? "Your package ({$userPackage->package->name}) will have ended by {$classDate->class_date->format('Y-m-d')}" : false;
            }

            if (! $classDate->class->is_free) {
                $boxFacility = $classDate->class->location;
                // user has sessions left for package period?
                $sessionsRemaining = $this->getSessionsRemainingForUserPackageForDate($userPackage, $boxFacility, $classDate->class_date);

                if (($userPackage->package->limit === 0 && $sessionsRemaining <= 0) || $sessionsRemaining <= 0) {

                    // Check if user has not reached their daily limit
                    if ($boxFacility->tenant->limit_inter_facility_bookings) {
                        $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $classDate->class_date, clone $classDate->class_date, $boxFacility->tenant, $boxFacility);
                    } else {
                        $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $classDate->class_date, clone $classDate->class_date, $boxFacility->tenant);
                    }

                    $remainingBookingsForDay = $boxFacility->max_bookings_per_athlete_per_day - $bookedForDate;

                    if ($remainingBookingsForDay <= 0 || $userPackage->package->limit === 0) {
                        return $isReturnErrorMessage ? 'You have reached your maximum bookings per day limit' : false;
                    } else {
                        return $isReturnErrorMessage ? "You do not have any sessions available on your package ({$userPackage->package->name})" : false;
                    }
                }
            }

            return $userPackage;
        }

        $userPackage = null;
        $userPackageErrors = '';

        /** @var UserPackage $userPackage */
        foreach ((new UserPackageService())->getActiveUserPackagesForTenant($athlete, $box, $classDate->date) as $userPackage) {
            // is package allowed for this class?
            if ($this->isPackageAllowedForClass($userPackage->package, $classDate->class) === false) {
                $userPackageErrors .= "Your package ({$userPackage->package->name}) is not allowed to book this class. ";

                continue;
            }

            // check if the class date is > the user's package end date
            if ($userPackage->end_date && $classDate->class_date > $userPackage->end_date) {
                $userPackageErrors .= "Your package ({$userPackage->package->name}) will have ended by {$classDate->class_date->format('Y-m-d')}. ";

                continue;
            }

            if (! $classDate->class->is_free) {
                $boxFacility = $classDate->class->location;

                // user has sessions left for package period?
                $sessionsRemaining = $this->getSessionsRemainingForUserPackageForDate($userPackage, $boxFacility, $classDate->class_date);

                if (($userPackage->package->limit === 0 && $sessionsRemaining <= 0) || $sessionsRemaining <= 0) {

                    // Check if user has not reached their daily limit
                    if ($boxFacility->tenant->limit_inter_facility_bookings) {
                        $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $classDate->class_date, clone $classDate->class_date, $boxFacility->tenant, $boxFacility);
                    } else {
                        $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $classDate->class_date, clone $classDate->class_date, $boxFacility->tenant);
                    }

                    $remainingBookingsForDay = $boxFacility->max_bookings_per_athlete_per_day - $bookedForDate;

                    if ($remainingBookingsForDay <= 0 || $userPackage->package->limit === 0) {
                        $userPackageErrors .= 'You have reached your maximum bookings per day limit. ';
                    } else {
                        $userPackageErrors .= 'You do not have any sessions available on any of your active packages. ';
                    }

                    continue;
                }
            }

            return $userPackage;
        }

        $userPackageErrors = empty($userPackageErrors)
            ? 'User does not have any active packages to make this booking.'
            : trim($userPackageErrors);

        return $isReturnErrorMessage ? $userPackageErrors : false;
    }

    public function isClassDateFull(ClassDate $classDate): bool
    {
        $limit = $this->getAttendanceLimitForClassDate($classDate);
        $attendance = $this->getNumberOfAthletesBookedForClassDate($classDate);

        return $attendance >= $limit;
    }

    public function getAttendanceLimitForClassDate(ClassDate $classDate): int
    {
        if ($classDate->limit === null) {
            return $classDate->class->class_limit ?? 999999;
        } else {
            return $classDate->limit ?? 9999999;
        }
    }

    public function getNumberOfAthletesBookedForClassDate(ClassDate $classDate): int
    {
        return ClassBooking::query()
            ->select(['class_bookings.class_booking_id'])
            ->where('class_bookings.class_to_date_id', '=', $classDate->getKey())
            ->where(function ($query) {
                $query->where('class_bookings.class_booking_status_id', '=', ClassBookingStatus::BOOKED->value)
                    ->orWhere('class_bookings.class_booking_status_id', '=', ClassBookingStatus::NO_SHOW->value);
            })
            ->orderBy('class_bookings.dt_added')
            ->count();
    }

    public function getSessionsRemainingForUserPackageForDate(UserPackage $userPackage, Location $boxFacility, ?DateTime $date = null, ?bool $excludeTopUps = false): ?int
    {
        if ($date === null) {
            $date = new DateTime('now', new DateTimeZone($boxFacility->timezone->name));
        }

        $package = $userPackage->package;

        if ($userPackage->package->tenant->limit_inter_facility_bookings) {
            $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $date, clone $date, $boxFacility->tenant, $boxFacility);
        } else {
            $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $date, clone $date, $boxFacility->tenant);
        }

        $remainingBookingsForDay = $boxFacility->max_bookings_per_athlete_per_day - $bookedForDate;

        $today = new DateTime();
        $today->setTime(23, 23, 59);

        // Check if the user has not exceeded the max bookings per day per facility
        if ($remainingBookingsForDay <= 0 || $package->limit === 0) {
            return $remainingBookingsForDay;
        } elseif ($package->hasLimitedSessions()) {
            return $userPackage->sessions_available;
        } else {
            if ($package->type === PackageType::WEEKLY) {
                $monday = clone $date;

                if ($date->format('l') === 'Monday') {
                    $sunday = clone $date;
                    $sunday->add(new DateInterval('P6D'));
                } elseif ($date->format('l') === 'Tuesday') {
                    $monday->sub(new DateInterval('P1D'));
                    $sunday = clone $date;
                    $sunday->add(new DateInterval('P5D'));
                } elseif ($date->format('l') === 'Wednesday') {
                    $monday->sub(new DateInterval('P2D'));
                    $sunday = clone $date;
                    $sunday->add(new DateInterval('P4D'));
                } elseif ($date->format('l') === 'Thursday') {
                    $monday->sub(new DateInterval('P3D'));
                    $sunday = clone $date;
                    $sunday->add(new DateInterval('P3D'));
                } elseif ($date->format('l') === 'Friday') {
                    $monday->sub(new DateInterval('P4D'));
                    $sunday = clone $date;
                    $sunday->add(new DateInterval('P2D'));
                } elseif ($date->format('l') === 'Saturday') {
                    $monday->sub(new DateInterval('P5D'));
                    $sunday = clone $date;
                    $sunday->add(new DateInterval('P1D'));
                } else {
                    $monday->sub(new DateInterval('P6D'));
                    $sunday = clone $date;
                }

                if ($userPackage->package->tenant->limit_inter_facility_bookings) {
                    $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $monday, $sunday, $boxFacility->tenant, $boxFacility, true, true);
                } else {
                    $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $monday, $sunday, $boxFacility->tenant, null, true, true);
                }

                $sessionsRemaining = $package->limit - $bookedForPeriod;

                if (! $excludeTopUps && $sessionsRemaining <= 0 && $userPackage->sessions_available > 0) {
                    return $userPackage->sessions_available;
                }

                return $sessionsRemaining;
            } elseif ($package->type === PackageType::MONTHLY) {
                $firstOfThisMonth = new DateTime();
                $firstOfThisMonth->setTimestamp(strtotime('first day of this month', $date->getTimestamp()));

                $lastOfThisMonth = new DateTime();
                $lastOfThisMonth->setTimestamp(strtotime('last day of this month + 11 hours 59 minutes 59 seconds', $date->getTimestamp()));

                if ($userPackage->package->tenant->limit_inter_facility_bookings) {
                    $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $firstOfThisMonth, $lastOfThisMonth, $boxFacility->tenant, $boxFacility, true, true);
                } else {
                    $bookedForPeriod = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $firstOfThisMonth, $lastOfThisMonth, $boxFacility->tenant, null, true, true);
                }

                $sessionsRemaining = $package->limit - $bookedForPeriod;

                if (! $excludeTopUps && $sessionsRemaining <= 0 && $userPackage->sessions_available > 0) {
                    return $userPackage->sessions_available;
                }

                return $sessionsRemaining;

            } else {
                return 0;
            }
        }
    }

    public function cancelBookingWaitingForClassDateByUser(ClassBookingWaitingList $classBookingWaiting, User $canceller): bool
    {
        $classDate = $classBookingWaiting->classDate;
        $timezone = $classDate->class->tenant->timezone;

        if (! $timezone) {
            if ($classDate->class->location->timezone !== null) {
                $timezone = $classDate->class->location->timezone;
            } else {
                $timezone = $classDate->class->tenant->timezone;
            }
        }

        $classStartTime = $this->getClassDateStartDateTime($classBookingWaiting->classDate);
        $now = new DateTime('now', new DateTimeZone($timezone->zone));

        // only check if in the past when user is attempting to cancel waiting list.
        // allow coach to cancel whenever.
        if ($canceller->getKey() == $classBookingWaiting->user_id && ($now > $classStartTime)) {
            return false;
        }

        $classBookingWaiting->update([
            'status' => ClassBookingWaitingStatus::CANCELLED->value,
        ]);

        return true;
    }

    public function getClassDateStartDateTime(ClassDate $classDate, ?string $timezone = null): Carbon
    {
        if (! $timezone) {
            $timezone = $this->getTimezoneForBoxFacilityOrBox($classDate->class->location)->zone;
        }

        if ($classDate->start_time !== null) {
            return Carbon::parse($classDate->class_date->format('Y-m-d').' '.$classDate->start_time->format('H:i:s'), $timezone);
        } else {
            return Carbon::parse($classDate->class_date->format('Y-m-d').' '.$classDate->class->start_time->format('H:i:s'), $timezone);
        }
    }

    public function getClassDateEndDateTime(ClassDate $classDate, ?string $timezone = null): Carbon
    {
        if (! $timezone) {
            $timezone = $this->getTimezoneForBoxFacilityOrBox($classDate->class->location)->zone;
        }

        if ($classDate->end_time !== null) {
            return Carbon::parse($classDate->class_date->format('Y-m-d').' '.$classDate->end_time->format('H:i:s'), $timezone);
        } else {
            return Carbon::parse($classDate->class_date->format('Y-m-d').' '.$classDate->class->end_time->format('H:i:s'), $timezone);
        }
    }

    public function getMyClassBookings(?Location $boxFacility = null, ?DateTime $startDateTime = null, ?DateTime $endDateTime = null, ?string $ordering = 'ASC'): Collection|int|array
    {
        $data = [];
        $startDate = $startDateTime instanceof DateTime ? clone $startDateTime : null;
        $endDate = $endDateTime instanceof DateTime ? clone $endDateTime : null;

        // Get class booking for dates
        $classBookings = (new ClassBookingsService())->getClassBookingsForUser(auth()->user(), $boxFacility, $startDate, $endDate, null, $ordering);

        foreach ($classBookings as $classBooking) {
            // Upcoming classBookings
            if ($startDateTime instanceof DateTime && ! $endDateTime instanceof DateTime) {
                $classDateEndDateTime = $this->getClassDateEndDateTime($classBooking->classDate);
                $classDateEndDateTime = new DateTime($classDateEndDateTime->format('Y-m-d H:i:s'), $startDateTime->getTimezone());

                // Check if classDateTime is after startDateTime
                if ($classDateEndDateTime->getTimestamp() < $startDateTime->getTimestamp()) {
                    continue;
                }
            }

            // Recent classBookings
            if ($endDateTime instanceof DateTime && ! $startDateTime instanceof DateTime) {
                $classDateEndDateTime = $this->getClassDateEndDateTime($classBooking->classDate);
                $classDateEndDateTime = new DateTime($classDateEndDateTime->format('Y-m-d H:i:s'), $endDateTime->getTimezone());

                // Check if classDateTime is after startDateTime
                if ($classDateEndDateTime->getTimestamp() > $endDateTime->getTimestamp()) {
                    continue;
                }
            }
        }

        return $classBookings;
    }

    public function deactivateClassDate(ClassDate $classDate, ?bool $isAutoCancelled = false): void
    {
        $crmService = resolve(CrmService::class);

        $class = $classDate->class;

        // Get class bookings for class date
        $classBookings = (new ClassBookingsService())->getBookingsForClassDate($classDate, [ClassBookingStatus::BOOKED->value]);

        // Notify class bookings
        foreach ($classBookings as $classBooking) {
            if ($classBooking->non_member_email && is_null($classBooking->lead_member_id)) {
                $name = $classBooking->non_member_name;
                $email = $classBooking->non_member_email;
            } else {
                $name = $classBooking->user->full_name;
                $email = $classBooking->user;
            }

            if ($classBooking->user?->isDiscoveryUser()) {
                $crmService->createScheduledEmailForNotification(
                    tenantOrLocation: $classDate->class->location,
                    context: 'discovery_booking_class_discontinued',
                    recipient: $classBooking->user,
                    data: (new DiscoveryNotificationsService())->buildDataArray($classBooking),
                );
            } else {
                // Create a scheduled email
                $crmService->createScheduledEmailForNotification(
                    tenantOrLocation: $classDate->class->location,
                    context: $isAutoCancelled ? 'class_auto_cancelled' : 'class_discontinued',
                    recipient: $email,
                    data: [
                        'member_name' => $name,
                        'class_name' => $classDate->name(),
                        'class_date' => $classDate->date->format('D, d F Y'),
                        'class_time' => $this->getClassDateStartDateTime($classDate)->format('H:i').' - '.$this->getClassDateEndDateTime($classDate)->format('H:i'),
                    ],
                );
            }

            $canceller = $isAutoCancelled ? $this->getCoachUserForClassDate($classDate) : auth()->user();

            // Cancel class booking for member and refund sessions where needed
            $this->cancelBookingForClassDateByUser($classBooking, $canceller, false);
        }

        // Notify class coaches
        $classCoaches = [];
        $headCoach = $this->getCoachUserForClassDate($classDate);
        $supportingCoach = $this->getSupportingCoachUserForClassDate($classDate);

        if ($headCoach instanceof User) {
            $classCoaches[] = $headCoach;
        }

        if ($supportingCoach instanceof User) {
            $classCoaches[] = $supportingCoach;
        }

        foreach ($classCoaches as $classCoach) {
            $crmService->createScheduledEmailForNotification(
                tenantOrLocation: $classDate->class->location,
                context: $isAutoCancelled ? 'coach_class_auto_cancelled' : 'coach_class_discontinued',
                recipient: $classCoach,
                data: [
                    'coach_name' => $classCoach->full_name,
                    'class_name' => $classDate->name(),
                    'class_date' => $classDate->date->format('D, d F Y'),
                    'class_time' => $this->getClassDateStartDateTime($classDate)->format('H:i').' - '.$this->getClassDateEndDateTime($classDate)->format('H:i'),
                ],
            );
        }

        // Deactivate class date
        $classDate->update(['is_active' => false]);

        if ($class->isOnceOff()) {
            $class->update(['is_active' => false, 'dt_deactivated' => now()]);
        }
    }

    public function cancelBookingForClassDateByUser(ClassBooking $classBooking, User $canceller, ?bool $isSendCancelNotification = true, ?bool $isLateCancellation = false): bool
    {
        $crmService = resolve(CrmService::class);

        $boxFacility = $classBooking->class->location;
        $classStartTime = $this->getClassDateStartDateTime($classBooking->classDate);
        $now = new DateTime('now', new DateTimeZone($classBooking->class->location->timezone->zone ?? $classBooking->class->location->tenant->timezone->zone));

        $coach = $this->getCoachUserForClassDate($classBooking->classDate);

        $classBookingWaiting = (new ClassBookingsWaitingService())->getNextClassBookingWaitingForClassDate($classBooking->classDate);
        $currentBookedAthletes = $this->getAthletesWhoAreBookedForClassDate($classBooking->classDate);
        $limit = $this->getAttendanceLimitForClassDate($classBooking->classDate);

        $bookingThresholdDateTime = $this->getLatestBookingDateTimeForClassDate($classBooking->classDate);
        $cancellationThresholdDateTime = $this->getLatestCancellationDateTimeForClassDate($classBooking->classDate);

        $lastCancellationMessage = '';

        // only check for late cancellation if athlete performed cancellation (not coach)
        if ($now > $cancellationThresholdDateTime && ($canceller->user_id === $classBooking->user_id || $isLateCancellation)) {
            // late cancellation needs to be processed
            $classBooking->status = ClassBookingStatus::CANCELLED_AFTER_THRESHOLD;
            $classBooking->save();

            // here we trigger late cancellation fee
            if ($classBooking->userPackage?->package?->late_cancellation_fee > 0) {
                (new InvoiceService())->generateLateCancellationInvoiceForClassBooking($classBooking);
            }

            $lastCancellationMessage = 'PLEASE NOTE: You have cancelled the class after the class threshold and therefore it will count towards your attendance.<br /><br />';
        } else {
            // was it a coach that cancelled, or athlete?
            if ($canceller->user_id !== $classBooking->user_id) {
                // cancelled by coach
                $classBooking->status = ClassBookingStatus::CANCELLED_BY_COACH;
                $classBooking->save();
            } else {
                if ($now > $classStartTime) {
                    return false;
                }

                // cancelled by athlete
                $classBooking->status = ClassBookingStatus::CANCELLED;
                $classBooking->save();
            }

            // add session back (if limited membership and not free class)
            if ($this->shouldSessionBeRefundedForClassBookingCancellation($classBooking)) {
                $classBooking->userPackage()->update([
                    'sessions_available' => $classBooking->userPackage->sessions_available + 1,
                ]);
            }
        }

        // Save before auto booking members from the waiting list into the class
        $thirtyMinutesBeforeClass = clone $classStartTime;
        $thirtyMinutesBeforeClass->modify('-30 minutes');

        // Auto booking waiting list members workflow. Only booking members in if the time is before booking threshold
        if ($now < $bookingThresholdDateTime && $now < $thirtyMinutesBeforeClass) {
            // Check if box max athlete bookings per day = 1
            if ($boxFacility->max_bookings_per_athlete_per_day == 1 && $classBookingWaiting) {
                // Check if this waiting list user has another booking for today.
                $existingBooking = (new ClassBookingsService())->getMemberBookingsForDates($classBookingWaiting->user, $classBooking->classDate->class_date, $classBooking->classDate->class_date, [ClassBookingStatus::BOOKED, ClassBookingStatus::CANCELLED_AFTER_THRESHOLD, ClassBookingStatus::NO_SHOW]);

                if (count($existingBooking) > 0) {
                    // Cancel the class waiting booking if the user is already booked into another class
                    $classBookingWaiting->update([
                        'status' => ClassBookingWaitingStatus::CANCELLED,
                    ]);

                    // Get the next user in the waiting list
                    $classBookingWaiting = (new ClassBookingsWaitingService())->getNextClassBookingWaitingForClassDate($classBooking->classDate);
                }
            }

            if ($classBookingWaiting && (count($currentBookedAthletes) - 1) < $limit) {
                // check that waiting booking athlete is still able to book into this class, otherwise cancel this person's waiting booking.
                while ($classBookingWaiting && $this->canAthleteBookForClassDate(classDate: $classBookingWaiting->classDate, athlete: $classBookingWaiting->user, userPackage: $classBookingWaiting->userPackage) === false) {
                    // cancel the waiting item
                    $classBookingWaiting->update([
                        'status' => ClassBookingWaitingStatus::CANCELLED,
                    ]);

                    // get the next waiting booking (null if it doesn't exist)
                    $classBookingWaiting = (new ClassBookingsWaitingService())->getNextClassBookingWaitingForClassDate($classBooking->classDate);
                }

                if ($classBookingWaiting) {
                    $classBookingWaiting->update([
                        'status' => ClassBookingWaitingStatus::BOOKED,
                    ]);

                    // book this person into class
                    $newClassBooking = ClassBooking::query()->forceCreateQuietly([
                        'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
                        'class_to_date_id' => $classBookingWaiting->classDate->getKey(),
                        'class_id' => $classBookingWaiting->class_id,
                        'user_id' => $classBookingWaiting->user->getKey(),
                        'dt_added' => $now,
                        'dt_modified' => $now,
                        'created_by_id' => $classBookingWaiting->user->getKey(),
                        'updated_by_id' => $classBookingWaiting->user->getKey(),
                        'is_checked_in' => false,
                        'user_package_id' => $classBookingWaiting->userPackage->getKey(),
                        'is_checked_out' => false,
                    ]);

                    $classDate = $classBooking->classDate;
                    $userPackage = $classBookingWaiting->userPackage;

                    $sessionsRemaining = $this->getSessionsRemainingForUserPackageForDate($userPackage, $classDate->class->location, $classDate->class_date, true);

                    // not a free class and on a limited package OR has top-up sessions and no package sessions remaining, deduct session
                    if (! $classDate->class->is_free && $userPackage->package->hasLimitedSessions()) {
                        $userPackage->update(['sessions_available' => $userPackage->sessions_available - 1]);
                    } elseif (! $classDate->class->is_free && ! $userPackage->package->hasLimitedSessions() && $sessionsRemaining <= 0 && $userPackage->sessions_available > 0) {
                        $userPackage->update(['sessions_available' => $userPackage->sessions_available - 1]);
                        $newClassBooking->update(['top_up_used' => true]);
                    }

                    $this->scheduleBookingFromWaitingListConfirmationEmail($newClassBooking);

                    // send push
                    if ($newClassBooking->classDate && $newClassBooking->classDate->class_date->format('Y-m-d') == $now->format('Y-m-d')) {
                        $dateText = 'today';
                    } else {
                        $dateText = 'on '.$newClassBooking->classDate->class_date->format('D, d M');
                    }

                    $crmService->createScheduledPushNotification(
                        title: 'Booking Update',
                        content: 'You have been auto-booked into a class from the waiting list: '.$newClassBooking->classDate->name().' '.$dateText.' at '.$newClassBooking->classDate->startTime(),
                        user: $classBookingWaiting->user,
                        tenant: $classBookingWaiting->class->tenant,
                        location: $classBookingWaiting->class->location,
                    );
                }
            }
        }

        // send email to coach
        if ($coach && $coach->getKey() !== $canceller->getKey() && $classBooking->user && $isSendCancelNotification) {
            if ($classBooking->user->isDiscoveryUser()) {
                $crmService->createScheduledEmailForNotification(
                    tenantOrLocation: $classBooking->class->location,
                    context: 'discovery_booking_cancelled_coach',
                    recipient: $coach,
                    data: (new DiscoveryNotificationsService)->buildDataArray($classBooking),
                );
            } else {
                $crmService->createScheduledEmailForNotification(
                    tenantOrLocation: $classBooking->class->location,
                    context: 'coach_cancel_message',
                    recipient: $coach,
                    data: [
                        'member_name' => $classBooking->user->name,
                        'member_surname' => $classBooking->user->surname,
                        'class_name' => $classBooking->classDate->name(),
                        'class_time' => $classStartTime->format('H:i'),
                        'class_booking_date' => $classStartTime->format('D, d F Y'),
                        'cancel_threshold_message' => $lastCancellationMessage,
                        'facility_name' => $classBooking->class->tenant->name,
                        'location_name' => $classBooking->class->location->name,
                    ]
                );
            }
        }

        if ($classBooking->user && $isSendCancelNotification) {
            if ($classBooking->user->isDiscoveryUser()) {

                //cancelled by user
                if ($classBooking->user->getAuthIdentifier() === $canceller->getKey()) {
                    $crmService->createScheduledEmailForNotification(
                        tenantOrLocation: $classBooking->class->location,
                        context: 'discovery_booking_cancelled_member',
                        recipient: $classBooking->user,
                        data: (new DiscoveryNotificationsService)->buildDataArray($classBooking),
                    );
                } else {
                    //cancelled by staff
                    $crmService->createScheduledEmailForNotification(
                        tenantOrLocation: $classBooking->class->location,
                        context: 'discovery_booking_coach_cancelled_member',
                        recipient: $classBooking->user,
                        data: (new DiscoveryNotificationsService)->buildDataArray($classBooking),
                    );
                }

            } else {
                // send email to athlete
                $crmService->createScheduledEmailForNotification(
                    tenantOrLocation: $classBooking->class->location,
                    context: 'cancel_class',
                    recipient: $classBooking->user,
                    data: [
                        'member_name' => $classBooking->user->name,
                        'member_surname' => $classBooking->user->surname,
                        'class_name' => $classBooking->classDate->name(),
                        'class_time' => $classStartTime->format('H:i'),
                        'class_booking_date' => $classStartTime->format('D, d F Y'),
                        'cancel_threshold_message' => $lastCancellationMessage,
                        'location_name' => $classBooking->class->location->name,
                    ]
                );
            }
        }

        return true;
    }

    public function getCoachUserForClassDate(ClassDate $classDate): ?User
    {
        if ($classDate->coach_id) {
            return $classDate->headCoach;
        }

        return ClassCoach::query()
            ->where('class_id', $classDate->class_id)
            ->where('coach_type_id', CoachType::HEAD_COACH->value)
            ->whereDate('dt_added', '<=', $classDate->class_date->toDateString())
            ->where(function ($query) use ($classDate) {
                $query->where('is_active', 1)
                    ->orWhere(function ($query) use ($classDate) {
                        $query->where('is_active', 0)
                            ->where('dt_modified', '>=', $classDate->class_date->toDateString());
                    });
            })
            ->latest('dt_added')
            ->limit(1)
            ->first()
            ?->user;
    }

    public function getAthletesWhoAreBookedForClassDate(ClassDate $classDate): array
    {
        $classBookings = ClassBooking::query()
            ->where('class_to_date_id', '=', $classDate->getKey())
            ->where('class_booking_status_id', '=', ClassBookingStatus::BOOKED->value)
            ->orderBy('dt_added')
            ->get();

        $athletes = [];

        /** @var ClassBooking $classBooking */
        foreach ($classBookings as $classBooking) {
            if ($classBooking->non_member_email && ! $classBooking->leadMember()->doesntExist()) {
                $nonMember = new User();

                $nonMember->setAttribute('email', $classBooking->non_member_email);

                if (! empty($classBooking->getName())) {
                    $nonMember->setAttribute('first_name', $classBooking->name);
                } else {
                    $nonMember->setAttribute('email', $classBooking->non_member_email);
                }

                $athletes[] = $nonMember;
            } elseif ($classBooking->leadMember()->exists()) {
                $leadUser = new User();
                $leadMember = $classBooking->leadMember;

                $leadUser
                    ->setAttribute('first_name', $leadMember->first_name)
                    ->setAttribute('last_name', $leadMember->last_name)
                    ->setAttribute('email', $leadMember->email);

                $athletes[] = $leadUser;
            } else {
                $athletes[] = $classBooking->user;
            }
        }

        return $athletes;
    }

    public function getLatestBookingDateTimeForClassDate(ClassDate $classDate): Carbon
    {
        $classDateTime = $this->getClassDateStartDateTime($classDate);

        return $classDateTime->sub(new DateInterval('PT'.$classDate->class->booking_threshold.'M'));
    }

    public function getLatestCancellationDateTimeForClassDate(ClassDate $classDate): Carbon
    {
        $classDateTime = $this->getClassDateStartDateTime($classDate);

        return $classDateTime->sub(new DateInterval('PT'.$classDate->class->cancellation_threshhold.'M'));
    }

    private function shouldSessionBeRefundedForClassBookingCancellation(ClassBooking $classBooking): bool
    {
        $userPackage = $classBooking->userPackage;
        if ($userPackage && (! $classBooking->class->is_free || $classBooking->user->is_redacted) && $userPackage->package->hasLimitedSessions()) {
            return true;
        } elseif ($classBooking->top_up_used) {
            return true;
        }

        return false;
    }

    public function getSupportingCoachUserForClassDate(ClassDate $classDate): ?User
    {
        if ($classDate->supporting_coach_id) {
            return $classDate->supportingCoach;
        }

        return ClassCoach::query()
            ->where('class_id', $classDate->class_id)
            ->where('coach_type_id', CoachType::SUPPORTING_COACH->value)
            ->whereDate('dt_added', '<=', $classDate->class_date->toDateString())
            ->where(function ($query) use ($classDate) {
                $query->where('is_active', 1)
                    ->orWhere(function ($query) use ($classDate) {
                        $query->where('is_active', 0)
                            ->where('dt_modified', '>=', $classDate->class_date->toDateString());
                    });
            })
            ->latest('dt_added')
            ->limit(1)
            ->first()
            ?->user;
    }

    public function getClassDateName(ClassDate $classDate): string
    {
        return $classDate->name ?: $classDate->class->name;
    }

    public function getClassDateDescription(ClassDate $classDate): string
    {
        return $classDate->description ?: $classDate->class->description;
    }

    public function sendMessageToClass(ClassDate $classDate, string $message, string $to): void
    {
        $class = $classDate->class;
        $classTime = $this->getClassDateStartDateTime($classDate)->format('H:i').' - '.$this->getClassDateEndDateTime($classDate)->format('H:i');

        if ($to === 'bookedMembers') {
            // get all class bookings
            $classBookings = (new ClassBookingsService())->getClassBookingsForClassDateAndStatus($classDate, [ClassBookingStatus::BOOKED->value, ClassBookingStatus::NO_SHOW->value]);

            foreach ($classBookings as $classBooking) {
                $this->sendMessageToClassBookingOrClassWaitingBooking($classBooking, $message);
            }
        } elseif ($to === 'waitingListMembers') {
            $classBookingsWaiting = $this->getAthletesWhoAreOnWaitingListForClassDate($classDate);

            foreach ($classBookingsWaiting as $classBookingWaiting) {
                $this->sendMessageToClassBookingOrClassWaitingBooking($classBookingWaiting, $message);
            }
        }

        // Create a scheduled email
        (new CrmService())->createScheduledEmailForNotification(
            tenantOrLocation: $class->location,
            context: 'confirmation_member_message',
            recipient: auth()->user(),
            data: [
                'coach_name' => auth()->user()->name,
                'coach_surname' => auth()->user()->surname,
                'coach_message' => nl2br($message),
                'class_name' => $classDate->name(),
                'class_time' => $classTime,
                'class_booking_date' => $classDate->buildDateTime()->format('D, d F Y'),
            ]
        );
    }

    public function sendMessageToClassBookingOrClassWaitingBooking($classBookingOrClassBookingWaiting, string $message): bool
    {
        $crmService = resolve(CrmService::class);

        $attendee = null;
        $classDate = $classBookingOrClassBookingWaiting->classDate;
        $class = $classDate->class;
        $classTime = $this->getClassDateStartDateTime($classDate)->format('H:i').' - '.$this->getClassDateEndDateTime($classDate)->format('H:i');

        if ($classBookingOrClassBookingWaiting instanceof ClassBooking) {
            // Checking if the member is a lead member
            if ($classBookingOrClassBookingWaiting->user?->getKey() == null && $classBookingOrClassBookingWaiting->leadMember?->getKey() != null) {
                $attendee = [
                    'name' => $classBookingOrClassBookingWaiting->leadMember->first_name,
                    'surname' => $classBookingOrClassBookingWaiting->leadMember->last_name,
                    'email' => $classBookingOrClassBookingWaiting->leadMember->email_address,
                ];
            } elseif ($classBookingOrClassBookingWaiting->user?->getKey() == null && $classBookingOrClassBookingWaiting->leadMember?->getKey() == null) {
                $attendee = [
                    'name' => $classBookingOrClassBookingWaiting->non_member_name,
                    'surname' => '',
                    'email' => $classBookingOrClassBookingWaiting->non_member_email,
                ];
            } else {
                $attendee = [
                    'name' => $classBookingOrClassBookingWaiting->user->name,
                    'surname' => $classBookingOrClassBookingWaiting->user->surname,
                    'email' => $classBookingOrClassBookingWaiting->user->email,
                    'userEntity' => $classBookingOrClassBookingWaiting->user->getKey(),
                ];
            }
        } elseif ($classBookingOrClassBookingWaiting instanceof ClassBookingWaitingList) {
            $attendee = [
                'name' => $classBookingOrClassBookingWaiting->user->name,
                'surname' => $classBookingOrClassBookingWaiting->user->surname,
                'email' => $classBookingOrClassBookingWaiting->user->email,
                'userEntity' => $classBookingOrClassBookingWaiting->user->getKey(),
            ];
        }

        if (! $attendee) {
            return false;
        }

        // Create a scheduled email
        $crmService->createScheduledEmailForNotification(
            tenantOrLocation: $class->location,
            context: 'member_message',
            recipient: $classBookingOrClassBookingWaiting->user ? $classBookingOrClassBookingWaiting->user : $attendee['email'],
            data: [
                'member_name' => $attendee['name'],
                'member_surname' => $attendee['surname'],
                'coach_name' => auth()->user()->name,
                'coach_surname' => auth()->user()->surname,
                'coach_message' => nl2br($message),
                'class_name' => $classDate->name(),
                'class_time' => $classTime,
                'class_booking_date' => $classDate->buildDateTime()->format('D, d F Y'),
            ]
        );

        if ($classBookingOrClassBookingWaiting->user) {
            $crmService->createScheduledPushNotification(
                title: 'Message From Instructor',
                content: $message,
                user: $classBookingOrClassBookingWaiting->user,
                tenant: $class->tenant,
                location: $class->location,
            );
        }

        return true;
    }

    public function getAthletesWhoAreOnWaitingListForClassDate(ClassDate $classDate): Collection|array
    {
        return ClassBookingWaitingList::query()
            ->where('class_to_date_id', $classDate->getKey())
            ->where('status', '=', 'waiting')
            ->orderBy('dt_added')
            ->get();
    }

    public function notifyClassBookingsOfClassDateChange(ClassDate $classDate, ?string $oldTimeText = null, ?string $newTimeText = null, ?string $classCoachText = null): void
    {
        $class = $classDate->class;

        $classBookings = (new ClassBookingsService())->getBookingsForClassDate($classDate, [ClassBookingStatus::BOOKED->value]);

        $crmService = resolve(CrmService::class);

        /** @var ClassBooking $classBooking */
        foreach ($classBookings as $classBooking) {
            if ($classBooking->user()->exists()) {
                $recipient = $classBooking->user;
            } elseif ($classBooking->leadMember()->exists()) {
                $recipient = $classBooking->leadMember;
            } else {
                $recipient = $classBooking->non_member_email;
            }

            if ($classBooking->user?->isDiscoveryUser()) {
                // Create a scheduled email
                $crmService->createScheduledEmailForNotification(
                    tenantOrLocation: $class->location,
                    context: 'discovery_booking_modified_member',
                    recipient: $recipient,
                    data: [
                        ...(new DiscoveryNotificationsService())->buildDataArray($classBooking->fresh()),
                        'old_class_time' => $oldTimeText,
                        'class_time' => $newTimeText,
                    ],
                );
            } else {
                // Create a scheduled email
                $crmService->createScheduledEmailForNotification(
                    tenantOrLocation: $class->location,
                    context: 'class_schedule_change',
                    recipient: $recipient,
                    data: [
                        'member_name' => $classBooking->getName(),
                        'member_surname' => $classBooking->getSurname(),
                        'class_name' => $classDate->name(),
                        'class_old_time' => $oldTimeText,
                        'class_new_time' => $newTimeText,
                        'class_date' => $classDate->buildDateTime()->format('D, d-m-Y'),
                        'class_coach' => $classCoachText,
                    ]
                );
            }
        }
    }

    public function canAthleteJoinWaitingListForClassDate(ClassDate $classDate, TenantUser $tenantUser, ?bool $isReturnErrorMessage = false): bool|string
    {
        $location = $classDate->class->location;

        // user status ok?
        if ($tenantUser->status != UserStatus::ACTIVE) {
            return $isReturnErrorMessage ? 'Your account is not active' : false;
        }

        if ($tenantUser->user_debit_status_id === UserDebitStatus::DEBIT_ORDER && in_array($location->payment_gateway_id, [PaymentGateway::SEPA->value, PaymentGateway::GO_CARDLESS->value])) {
            if ($location->payment_gateway_id === PaymentGateway::SEPA->value) {
                $latestMandate = (new MandateService())->getLatestMandate($tenantUser->user, $location->tenant_id, MandateType::SEPA);

                if (! $latestMandate instanceof Mandate || $latestMandate->status !== MandateStatus::ACTIVE) {
                    $link = str(config('octiv.web_app_url'))->append('/sign/mandate')->toString();

                    return $isReturnErrorMessage ? "You do not have an active mandate. Please sign one: $link" : false;
                }
            }

            if ($location->payment_gateway_id === PaymentGateway::GO_CARDLESS->value) {
                $isOnboarded = (new GoCardlessService())->isUserOnBoard($tenantUser->user, $location);

                if (! $isOnboarded) {
                    try {
                        $link = (new GoCardlessService())->beginUserOnBoardingFlow($tenantUser->user, $location);

                        return $isReturnErrorMessage ? "You do not have an active mandate. Please sign one: $link" : false;
                    } catch (\Exception $e) {
                        return $isReturnErrorMessage ? $e->getMessage() : false;
                    }
                }
            }
        }

        // does user already have a booking waiting for this class date?
        if ((new ClassBookingsWaitingService())->getBookingWaitingForAthleteAndClassDate($tenantUser->user, $classDate)) {
            return $isReturnErrorMessage ? 'You have already on the waiting list for this class' : false;
        }

        $locationUser = (new TenantUserService())->getLocationUserByTenant($tenantUser->user, $tenantUser->tenant);

        if ($classDate->class->tenant->limit_inter_facility_bookings && $locationUser?->location_id !== $classDate->class->location->getKey()) {
            return $isReturnErrorMessage ? "You are only allowed to make bookings at {$locationUser?->location->name}" : false;
        }

        // is class and class date active?
        if (! $classDate->is_active) {
            return $isReturnErrorMessage ? 'Class is not active' : false;
        }

        // current time is within allowed threshold prior to class?
        $latestBookingTime = $this->getClassDateStartDateTime($classDate);
        $latestBookingTime->sub(new DateInterval('PT'.$classDate->class->booking_threshold.'M'));

        $userPackageErrors = '';

        $userPackages = ((new UserPackageService())->getActiveUserPackagesForTenant($tenantUser->user, $tenantUser->tenant, $classDate->class_date));

        if ($userPackages->isEmpty()) {
            return $isReturnErrorMessage ? 'User has no active packages.' : false;
        }

        foreach ($userPackages as $userPackage) {
            // is package allowed for this class?
            if ($this->isPackageAllowedForClass($userPackage->package, $classDate->class) === false) {
                $userPackageErrors .= $isReturnErrorMessage ? "Your package ({$userPackage->package->name}) is not allowed to book this class" : false;

                continue;
            }

            // check if the class date is > the user's package end date
            if ($userPackage->end_date && $classDate->class_date > $userPackage->end_date) {
                $userPackageErrors .= $isReturnErrorMessage ? "Your package ({$userPackage->package->name}) will have ended by {$classDate->class_date->format('Y-m-d')}" : false;

                continue;
            }

            if (! $classDate->class->is_free) {
                // user has sessions left for package period?
                $sessionsRemaining = $this->getSessionsRemainingForUserPackageForDate($userPackage, $location, $classDate->class_date);

                if ($sessionsRemaining <= 0) {
                    if ($isReturnErrorMessage) {
                        // Check if user has not reached their daily limit
                        if ($location->tenant->limit_inter_facility_bookings) {
                            $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $classDate->class_date, clone $classDate->class_date, $location->tenant, $location);
                        } else {
                            $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $classDate->class_date, clone $classDate->class_date, $location->tenant);
                        }

                        $remainingBookingsForDay = $location->max_bookings_per_athlete_per_day - $bookedForDate;

                        if ($remainingBookingsForDay <= 0 || $userPackage->package->type === 0) {
                            return 'You have reached your maximum bookings per day limit';
                        }
                    }

                    $userPackageErrors .= $isReturnErrorMessage ? "You do not have any sessions available on your package ({$userPackage->package->name})" : false;

                    continue;
                }
            }

            return true;
        }

        return $isReturnErrorMessage ? $userPackageErrors : false;
    }

    public function deleteFutureBookingsForRecurringBookingDaysOfWeek(ClassRecurringBooking $booking, array $daysOfWeek = []): void
    {
        /**
         * Workaround for  MySQL DAYOFWEEK() starting on Sunday
         */
        $lookup = [];

        foreach ($daysOfWeek as $internalId) {
            if ($internalId === 7) {
                $lookup[] = $internalId = 1;
            } else {
                $lookup[] = $internalId + 1; // our internal day IDs are -1 from MySQL day of week numbers
            }
        }

        $classBookings = ClassBooking::query()
            ->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->whereIn(DB::raw('DAYOFWEEK(class_to_dates.class_date)'), $lookup)
            ->where('class_bookings.user_id', '=', $booking->user_id)
            ->where('class_bookings.class_id', '=', $booking->class_id)
            ->where('class_to_dates.class_date', '>', today()->toDateString())
            ->where('class_bookings.class_booking_status_id', '=', 1)
            ->get();

        foreach ($classBookings as $classBooking) {
            $class = ClassBooking::query()->find($classBooking->getKey());

            if (! $class instanceof ClassBooking) {
                continue;
            }

            $this->deleteBookingForClassDateByUser($class, $booking->user);
        }
    }

    public function deleteBookingForClassDateByUser(ClassBooking $classBooking, User $canceller): bool
    {
        $crmService = resolve(CrmService::class);

        $boxFacility = $classBooking->class->location;
        $classStartTime = $this->getClassDateStartDateTime($classBooking->classDate);
        $now = today();

        $coach = $this->getCoachUserForClassDate($classBooking->classDate);

        $classBookingWaiting = (new ClassBookingsWaitingService())->getNextClassBookingWaitingForClassDate($classBooking->classDate);
        $currentBookedAthletes = $this->getAthletesWhoAreBookedForClassDate($classBooking->classDate);
        $limit = $this->getAttendanceLimitForClassDate($classBooking->classDate);

        $bookingThresholdDateTime = $this->getLatestBookingDateTimeForClassDate($classBooking->classDate);
        $cancellationThresholdDateTime = $this->getLatestCancellationDateTimeForClassDate($classBooking->classDate);

        $lastCancellationMessage = '';

        // only check for late cancellation if athlete performed cancellation (not coach)
        if ($now > $cancellationThresholdDateTime && $canceller->getKey() == $classBooking->user->getKey()) {
            // late cancellation needs to be processed
            $classBooking->update([
                'class_booking_status_id' => ClassBookingStatus::CANCELLED_AFTER_THRESHOLD->value,
            ]);

            $lastCancellationMessage = 'PLEASE NOTE: You have cancelled the class after the class threshold and therefore it will count towards your attendance.<br /><br />';
        } else {
            // was it a coach that cancelled, or athlete?
            if ($canceller->getKey() !== $classBooking->user->getKey()) {
                // cancelled by coach
                $classBooking->update([
                    'class_booking_status_id' => ClassBookingStatus::CANCELLED_BY_COACH->value,
                ]);
            } else {
                if ($now > $classStartTime) {
                    return false;
                }

                // cancelled by athlete
                $classBooking->update([
                    'class_booking_status_id' => ClassBookingStatus::CANCELLED->value,
                ]);
            }

            // add session back (if limited membership and not free class)
            if ($this->shouldSessionBeRefundedForClassBookingCancellation($classBooking)) {
                $classBooking->userPackage->update([
                    'sessions_available' => $classBooking->userPackage->sessions_available + 1,
                ]);
            }
        }

        // Save before auto booking members from the waiting list into the class
        $thirtyMinutesBeforeClass = clone $classStartTime;
        $thirtyMinutesBeforeClass->modify('-30 minutes');

        // Auto booking waiting list members workflow. Only booking members in if the time is before booking threshold
        if ($now < $bookingThresholdDateTime && $now < $thirtyMinutesBeforeClass) {
            // Check if box max athlete bookings per day = 1
            if ($boxFacility->max_bookings_per_athlete_per_day == 1 && $classBookingWaiting) {
                // Check if this waiting list user has another booking for today.
                $existingBooking = (new ClassBookingsService())->getMemberBookingsForDates($classBookingWaiting->user, $classBooking->classDate->class_date, $classBooking->classDate->class_date, [ClassBookingStatus::BOOKED->value, ClassBookingStatus::CANCELLED_AFTER_THRESHOLD->value, ClassBookingStatus::NO_SHOW->value]);

                if (count($existingBooking) > 0) {
                    // Cancel the class waiting booking if the user is already booked into another class
                    $classBookingWaiting->update([
                        'status' => ClassBookingWaitingStatus::CANCELLED,
                    ]);

                    // Get the next user in the waiting list
                    $classBookingWaiting = (new ClassBookingsWaitingService())->getNextClassBookingWaitingForClassDate($classBooking->classDate);
                }
            }

            if ($classBookingWaiting instanceof ClassBookingWaitingList && (count($currentBookedAthletes) - 1) < $limit) {
                // check that waiting booking athlete is still able to book into this class, otherwise cancel this person's waiting booking.
                while ($classBookingWaiting instanceof ClassBookingWaitingList && $this->canAthleteBookForClassDate($classBookingWaiting->classDate, $classBookingWaiting->user) === false) {
                    // cancel the waiting item
                    $classBookingWaiting->update([
                        'status' => ClassBookingWaitingStatus::CANCELLED,
                    ]);

                    // get the next waiting booking (null if it doesn't exist)
                    $classBookingWaiting = (new ClassBookingsWaitingService())->getNextClassBookingWaitingForClassDate($classBooking->classDate);
                }

                if ($classBookingWaiting instanceof ClassBookingWaitingList) {
                    $classBookingWaiting->update([
                        'status' => ClassBookingWaitingStatus::BOOKED->value,
                    ]);

                    // book this person into class
                    $newClassBooking = ClassBooking::query()->create([
                        'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
                        'class_id' => $classBooking->class->getKey(),
                        'class_to_date_id' => $classBooking->classDate->getKey(),
                        'is_checked_in' => false,
                        'user_id' => $classBookingWaiting->user_id,
                        'created_by_id' => $classBookingWaiting->user_id,
                        'user_package_id' => $classBookingWaiting->userPackage->getKey(),

                    ]);

                    $classDate = $classBooking->classDate;
                    $userPackage = $classBookingWaiting->userPackage;

                    $sessionsRemaining = $this->getSessionsRemainingForUserPackageForDate($userPackage, $classDate->class->location, $classDate->class_date, true);

                    // not a free class and on a limited package OR has top-up sessions and no package sessions remaining, deduct session
                    if (! $classDate->class->is_free && $userPackage->package->hasLimitedSessions()) {
                        $userPackage->update([
                            'sessions_available' => $userPackage->sessions_available - 1,
                        ]);
                    } elseif (! $classDate->class->is_free && ! $userPackage->package->hasLimitedSessions() && $sessionsRemaining <= 0 && $userPackage->sessions_available > 0) {
                        $userPackage->update([
                            'sessions_available' => $userPackage->sessions_available - 1,
                        ]);
                        $classBooking->update([
                            'top_up_used' => true,
                        ]);
                    }

                    // send email
                    $this->scheduleBookingFromWaitingListConfirmationEmail($newClassBooking);

                    // send push
                    if ($classBooking->classDate && $classBooking->classDate->date->format('Y-m-d') == $now->format('Y-m-d')) {
                        $dateText = 'today';
                    } else {
                        $dateText = 'on '.$classBooking->classDate->date->format('D, d M');
                    }

                    $crmService->createScheduledPushNotification(
                        title: 'Booking Update',
                        content: 'You have been auto-booked into a class from the waiting list: '.$classBooking->class->name.' '.$dateText.' at '.$classStartTime->format('H:i'),
                        user: $classBookingWaiting->user,
                        tenant: $classBookingWaiting->class->tenant,
                        location: $classBookingWaiting->class->location,
                    );
                }
            }
        }

        // send email to coach
        if ($coach && $coach !== $canceller && $classBooking->user) {
            $crmService->createScheduledEmailForNotification(
                tenantOrLocation: $classBooking->class->location,
                context: 'coach_cancel_message',
                recipient: $coach,
                data: [
                    'member_name' => $classBooking->user->name,
                    'member_surname' => $classBooking->user->surname,
                    'class_name' => $classBooking->classDate->name(),
                    'class_time' => $classStartTime->format('H:i'),
                    'class_booking_date' => $classStartTime->format('D, d F Y'),
                    'cancel_threshold_message' => $lastCancellationMessage,
                    'facility_name' => $classBooking->class->tenant->name,
                    'location_name' => $classBooking->class->location->name,
                ]
            );
        }

        //send email to user
        if ($classBooking->user) {
            $crmService->createScheduledEmailForNotification(
                tenantOrLocation: $classBooking->class->location,
                context: 'cancel_class',
                recipient: $classBooking->user,
                data: [
                    'member_name' => $classBooking->user->name,
                    'member_surname' => $classBooking->user->surname,
                    'class_name' => $classBooking->classDate->name(),
                    'class_time' => $classStartTime->format('H:i'),
                    'class_booking_date' => $classStartTime->format('D, d F Y'),
                    'cancel_threshold_message' => $lastCancellationMessage,
                    'location_name' => $classBooking->class->location->name,
                ]
            );
        }

        // Delete the class booking permanently
        $classBooking->delete();

        return true;
    }

    public function ensureBookingsForRecurringBooking(ClassRecurringBooking $booking): void
    {
        $classDates = ClassDate::query()
            ->select('class_to_dates.*')
            ->leftJoin('class_bookings', function ($join) use ($booking) {
                $join->on('class_bookings.class_to_date_id', '=', 'class_to_dates.class_to_date_id')
                    ->where('class_bookings.user_id', '=', $booking->user_id)
                    ->whereIn('class_booking_status_id', [1, 2, 3, 4, 5, 6]);
            })
            ->where('class_to_dates.class_date', '>=', today()->toDateString())
            ->where('class_to_dates.is_active', '=', 1)
            ->where('class_to_dates.class_id', '=', $booking->class_id)
            ->whereNull('class_bookings.class_booking_id')
            ->get();

        foreach ($classDates as $classDate) {
            if ($booking->hasDayOfWeek($classDate->date->format('l')) && ! $booking->isDeactivatedOnDate($classDate->class_date)) {
                $this->createRecurringBookingsForClassDateAndUser($classDate, $booking->user);
            }
        }
    }

    public function createRecurringBookingsForClassDateAndUser(ClassDate $classDate, User $user): void
    {
        if ($this->isClassDateFull($classDate)) {
            return;
        }

        if (! ($userPackage = $this->canRecurringBookingAthleteBookForClassDate($classDate, $user))) {
            return;
        }

        // is this user on waiting list for this class?
        $classBookingWaiting = (new ClassBookingsWaitingService())->getWaitingForClassAndDateAndUser($classDate->class, $classDate, $user);

        if ($classBookingWaiting instanceof ClassBookingWaitingList) {
            $this->cancelBookingWaitingForClassDateByUser($classBookingWaiting, $user);
        }

        $classBooking = ClassBooking::query()->create([
            'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
            'class_id' => $classDate->class->getKey(),
            'class_to_date_id' => $classDate->getKey(),
            'user_id' => $user->getKey(),
            'user_package_id' => $userPackage->getKey(),
        ]);

        $sessionsRemaining = $this->getSessionsRemainingForUserPackageForDate($userPackage, $classDate->class->location, $classDate->class_date, true);

        // not a free class and on a limited package OR has top-up sessions and no package sessions remaining, deduct session
        if (! $classDate->class->is_free && $userPackage->package->hasLimitedSessions()) {
            $userPackage->update(['sessions_available' => $userPackage->sessions_available - 1]);
        } elseif (! $classDate->class->is_free && ! $userPackage->package->hasLimitedSessions() && $sessionsRemaining <= 0 && $userPackage->sessions_available > 0) {
            $userPackage->update(['sessions_available' => $userPackage->sessions_available - 1]);
            $classBooking->update(['top_up_used' => true]);
        }

        if ($this->sendEmails) {
            $this->scheduleBookingConfirmationEmail($classBooking);
        }

        $this->recurringBookingsGenerated++;
    }

    public function canRecurringBookingAthleteBookForClassDate(ClassDate $classDate, User $athlete)
    {
        $now = new DateTime('now');
        $startTime = $this->getClassDateStartDateTime($classDate);

        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($athlete, $classDate->class->tenant);

        if (! $userBoxMembership instanceof TenantUser) {
            return false;
        }

        // is class and class date active?
        if (! $classDate->is_active) {
            return false;
        }

        // is class in future?
        if ($startTime < $now) {
            return false;
        }

        // user status ok?
        if ($userBoxMembership->user_status_id !== UserStatus::ACTIVE) {
            return false;
        }

        // does user already have a booking for this class date?
        if ((new ClassBookingsService())->getBookingForAthleteAndClassDate($athlete, $classDate)) {
            return false;
        }

        foreach ((new UserPackageService())->getActiveUserPackagesForTenant($athlete, $classDate->class->tenant, $classDate->class_date) as $userPackage) {

            // is package allowed for this class?
            if ($this->isPackageAllowedForClass($userPackage->package, $classDate->class) === false) {
                continue;
            }

            // check if the class date is > the user's package end date
            if ($userPackage->end_date && $classDate->date->gt($userPackage->end_date)) {
                continue;
            }

            if (! $classDate->class->isFree()) {
                $location = $classDate->class->location;

                // user has sessions left for package period?
                $sessionsRemaining = $this->getSessionsRemainingForUserPackageForDate($userPackage, $location, $classDate->date);

                if (($userPackage->package->limit === 0 && $sessionsRemaining <= 0) || $sessionsRemaining <= 0) {
                    continue;
                }
            }

            return $userPackage;
        }

        return false;
    }

    public function deleteFutureClassBookingsForRecurringBookingAfterEndDate(ClassRecurringBooking $classRecurringBooking, $deactivateOn): void
    {
        $futureClassBookings = (new ClassBookingsService())->getFutureClassBookingsForClassRecurringBookingsFromDate($classRecurringBooking, $deactivateOn);

        foreach ($futureClassBookings as $futureClassBooking) {
            $this->deleteBookingForClassDateByUser($futureClassBooking, auth()->user());
        }

    }

    public function createClassBookingForClassDateAndCoachByUser(ClassDate $classDate, User $coach, User $user): Builder|Model
    {
        $booking = ClassBooking::query()->create([
            'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
            'class_id' => $classDate->class->getKey(),
            'class_to_date_id' => $classDate->getKey(),
            'user_id' => $coach->getKey(),
            'created_by_id' => $user->getKey(),
        ]);

        $this->scheduleBookingConfirmationEmail($booking);

        return $booking;
    }

    public function createClassBookingForClassDateAndLeadByUser(ClassDate $classDate, LeadMember $lead, ?User $user = null, ?UserPackage $userPackage = null): Builder|Model
    {
        if (! $userPackage) {
            $userPackage = UserPackage::where('user_id', $lead->user_id)
                ->whereIn('package_id', $classDate->class->classPackages->pluck('package_id'))
                ->where('sessions_available', '!=', 0)
                ->orderBy('user_to_package_id')
                ->orderBy('effective_date')
                ->orderByDesc('sessions_available')
                ->first();
        }

        $classBooking = ClassBooking::query()->create([
            'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
            'class_id' => $classDate->class->getKey(),
            'class_to_date_id' => $classDate->getKey(),
            'is_checked_in' => false,
            'user_id' => $lead->user_id,
            'created_by_id' => $user?->getKey(),
            'non_member_email' => $lead->email,
            'non_member_name' => $lead->first_name.' '.$lead->last_name,
            'lead_member_id' => $lead->getKey(),
            'user_package_id' => $userPackage?->getKey(),
        ]);

        // FIFO, get user package for booking
        $userPackage?->update(['sessions_available' => $userPackage->sessions_available - 1]);

        $this->scheduleBookingConfirmationEmail($classBooking);

        return $classBooking;
    }

    public function createClassBookingForClassDateAndNonMemberByUser(ClassDate $classDate, string $email, string $name, User $user): Builder|Model
    {
        $booking = ClassBooking::query()->create([
            'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
            'class_id' => $classDate->class->getKey(),
            'class_to_date_id' => $classDate->getKey(),
            'is_checked_in' => false,
            'user_id' => null,
            'created_by_id' => $user?->getKey(),
            'non_member_email' => $email,
            'non_member_name' => $name,
            'lead_member_id' => null,
        ]);

        $this->scheduleBookingConfirmationEmail($booking);

        return $booking;
    }

    public function getAllActiveClassesByBoxFacility(Location $boxFacility): Collection|array
    {
        return Classes::query()
            ->where('classes.box_facility_id', '=', $boxFacility->getKey())
            ->where('classes.is_active', '=', 1)
            ->orderBy('classes.class_name')
            ->get();
    }

    public function getTimezoneForBoxFacilityOrBox($boxOrBoxFacility): Timezone
    {
        $timezone = null;

        if ($boxOrBoxFacility instanceof Location) {
            if ($boxOrBoxFacility->timezone instanceof Timezone) {
                // Get timezone for boxFacility
                $timezone = $boxOrBoxFacility->timezone;
            } else {
                // Get timezone for box form the boxFacility
                $timezone = $boxOrBoxFacility->tenant->timezone;
            }
        } elseif ($boxOrBoxFacility instanceof Tenant) {
            $timezone = $boxOrBoxFacility->timezone;
        }

        if (! $timezone instanceof Timezone) {
            $timezone = new Timezone();
            $timezone->setAttribute('zone', 'UTC');
        }

        return $timezone;
    }

    /**
     * Creates and schedules the booking confirmation email for given class booking.
     */
    private function scheduleBookingConfirmationEmail(ClassBooking $classBooking): void
    {
        $start = $this->getClassDateStartDateTime($classBooking->classDate);

        if ($classBooking->non_member_email && is_null($classBooking->lead_member_id)) {
            $name = $classBooking->non_member_name;
            $surname = '';
            $email = $classBooking->non_member_email;
        } else {
            $name = $classBooking->user->name;
            $surname = $classBooking->user->surname;
            $email = $classBooking->user;
        }

        (new CrmService())->createScheduledEmailForNotification(
            tenantOrLocation: $classBooking->class->location,
            context: 'book_class',
            recipient: $email,
            data: [
                'member_name' => $name,
                'member_surname' => $surname,
                'class_name' => $classBooking->classDate->name(),
                'class_time' => $start->format('H:i'),
                'class_booking_date' => $start->format('D, d F Y'),
                'facility_name' => $classBooking->class->tenant->name,
                'location_name' => $classBooking->class->location->name,
            ]
        );
    }

    private function scheduleBookingFromWaitingListConfirmationEmail(ClassBooking $classBooking): void
    {
        $start = $this->getClassDateStartDateTime($classBooking->classDate);

        if ($classBooking->non_member_email && is_null($classBooking->lead_member_id)) {
            $name = $classBooking->non_member_name;
            $surname = '';
            $email = $classBooking->non_member_email;
        } else {
            $name = $classBooking->user->name;
            $surname = $classBooking->user->surname;
            $email = $classBooking->user;
        }

        (new CrmService())->createScheduledEmailForNotification(
            tenantOrLocation: $classBooking->class->location,
            context: 'waitinglist_booking',
            recipient: $email,
            data: [
                'member_name' => $name,
                'member_surname' => $surname,
                'class_name' => $classBooking->classDate->name(),
                'class_time' => $start->format('H:i'),
                'class_booking_date' => $start->format('D, d F Y'),
                'facility_name' => $classBooking->class->tenant->name,
                'location_name' => $classBooking->class->location->name,
            ]
        );
    }

    public function updateClassDays(Classes $class, array $classRecurringDays, Carbon $fromDate): void
    {
        /** @var CrmService $crmService */
        $crmService = resolve(CrmService::class);

        /** @var ClassDateService $classDateService */
        $classDateService = resolve(ClassDateService::class);

        $currentDayOfWeekDays = $class->getActiveClassDays()->pluck('day_id')->toArray();

        // Iterate through old array and match it with new one
        foreach ($class->getActiveClassDays() as $classDay) {
            // If any of the old ones are in the new one skip them. All is good then
            if (in_array($classDay->day_id, $classRecurringDays)) {
                continue;
            }

            // If any of the old ones are not in the new one, deactivate them and the bookings that have already been made on them

            // Get class date for class
            $futureClassDates = $classDateService->getUpcomingClassDatesForClass($class, $fromDate);

            /** @var ClassDate $futureClassDate */
            foreach ($futureClassDates as $futureClassDate) {
                // Check if classDate day is the same as old one deactivate all class bookings
                if ((int) $futureClassDate->date->format('N') !== $classDay->day_id) {
                    continue;
                }

                // Get class bookings
                $classBookings = ClassBooking::query()
                    ->where('class_id', $class->getKey())
                    ->where('class_to_date_id', $futureClassDate->getKey())
                    ->where('class_booking_status_id', ClassBookingStatus::BOOKED)
                    ->whereNotNull('user_id')
                    ->get();

                /** @var ClassBooking $classBooking */
                foreach ($classBookings as $classBooking) {
                    $user = $classBooking->user;

                    if ($user->isDiscoveryUser()) {
                        $crmService->createScheduledEmailForNotification(
                            tenantOrLocation: $class->location,
                            context: 'discovery_booking_class_discontinued',
                            recipient: $user->email,
                            data: (new DiscoveryNotificationsService())->buildDataArray($classBooking),
                        );
                    } else {
                        // Create a scheduled email
                        $crmService->createScheduledEmailForNotification(
                            tenantOrLocation: $class->location,
                            context: 'class_day_discontinued',
                            recipient: $user->email,
                            data: [
                                'member_name' => $user->name,
                                'member_surname' => $user->surname,
                                'class_name' => $class->name,
                                'old_class_name' => $class->name,
                                'old_class_date' => $futureClassDate->date->format('D, d F Y'),
                                'old_class_time' => $this->getClassDateStartDateTime($futureClassDate)->format('H:i').' - '.$this->getClassDateEndDateTime($futureClassDate)->format('H:i'),
                            ],
                        );
                    }

                    // Cancel class booking for member and refund sessions where needed
                    $this->cancelBookingForClassDateByUser($classBooking, auth()->user(), false);

                    // Remove class bookings
                    $classBooking->delete();
                }

                // Deactivate class date
                $futureClassDate->update(['is_active' => false]);
            }

            // Deactivate class day
            $classDay->update(['is_active' => false]);
        }

        // Iterate through new array and check that they are contained in the old array.
        // If one is not, add new day to running array and at end, then generate class dates
        foreach ($classRecurringDays as $dayOfWeekId) {
            if (! in_array($dayOfWeekId, $currentDayOfWeekDays)) {
                $class->daysOfWeek()->create(['day_id' => $dayOfWeekId]);
            }
        }
    }

    public function removeClassDatesAfterEndDate(Classes $class, Carbon $endDate): void
    {
        $futureClassDates = (new ClassDateService())->getUpcomingClassDatesForClass($class, $endDate);

        /** @var ClassDate $futureClassDate */
        foreach ($futureClassDates as $futureClassDate) {
            // Get class bookings
            $classBookings = ClassBooking::query()
                ->where('class_id', $class->getKey())
                ->where('class_to_date_id', $futureClassDate->getKey())
                ->get();

            /** @var ClassBooking $classBooking */
            foreach ($classBookings as $classBooking) {
                if ($classBooking->isBooked()) {
                    // Cancel class booking for member and refund sessions where needed
                    $this->cancelBookingForClassDateByUser($classBooking, auth()->user());
                }

                $classBooking->delete();
            }

            // Remove users that are on the waiting list
            ClassBookingWaitingList::query()
                ->where('class_id', $class->getKey())
                ->where('class_to_date_id', $futureClassDate->getKey())
                ->delete();

            $futureClassDate->delete();
        }
    }

    public function canPackageLeadMemberBookForClassDate(ClassDate $classDate, ?Package $package = null, ?bool $isReturnErrorMessage = false, ?LeadMember $leadMember = null): bool|string
    {
        $returnCode = true;

        if (! $leadMember) {
            $leadMember = LeadMember::query()
                ->where('user_id', '=', auth()->user()->getAuthIdentifier())
                ->where('box_facility_id', '=', $classDate->class->location->getKey())
                ->first();
        }

        if (! $leadMember) {
            return $isReturnErrorMessage ? 'Lead Member not found' : false;
        }

        if (! $package) {
            $classPackageIds = $classDate->class->classPackages->pluck('package_id');
            $packages = UserPackage::query()
                ->where('user_id', $leadMember->user_id)
                ->whereIn('package_id', $classPackageIds->toArray())
                ->where('sessions_available', '!=', 0)
                ->groupBy('package_id')
                ->pluck('package_id')->toArray();

            if (empty($packages)) {
                return $returnCode = $isReturnErrorMessage ? 'Lead is not allowed to book for this class' : false;
            }
        }

        if ($leadMember->location instanceof Location) {
            $timezone = $this->getTimezoneForBoxFacilityOrBox($leadMember->location);
        } else {
            $timezone = $this->getTimezoneForBoxFacilityOrBox($classDate->class->location);
        }

        // does user already have a booking for this class date?
        if ($this->getBookingForLeadMemberAndClassDate($leadMember, $classDate)) {
            $returnCode = $isReturnErrorMessage ? 'You have already booked this class' : false;
        }

        // is class and class date active?
        if (! $classDate->is_active) {
            $returnCode = $isReturnErrorMessage ? 'Class date is not active' : false;
        }

        // check if class is fully booked
        if ($this->isClassDateFull($classDate)) {
            $returnCode = $isReturnErrorMessage ? 'Class is fully booked' : false;
        }

        $sessionsLeft = UserPackage::where('user_id', $leadMember->user_id)
            ->whereIn('package_id', $packages)
            ->sum('sessions_available');

        // user has sessions left for package period?
        if ($sessionsLeft <= 0) {
            $returnCode = $isReturnErrorMessage ? 'No more sessions remaining' : false;
        }

        $now = new DateTime('now', new DateTimeZone($timezone->zone));

        // is class in future?
        if ($this->getClassDateEndDateTime($classDate)->toDateTime() < $now) {
            return $isReturnErrorMessage ? 'This class is in the past' : false;
        }

        return $returnCode;
    }

    public function getBookingForLeadMemberAndClassDate(LeadMember $leadMember, ClassDate $classDate): bool
    {
        return ClassBooking::query()
            ->where('class_bookings.class_to_date_id', '=', $classDate->getKey())
            ->where('class_bookings.user_id', '=', $leadMember->user_id)
            ->where('class_bookings.lead_member_id', '=', $leadMember->member_id)
            ->whereNested(function ($query) {
                $query->where('class_bookings.class_booking_status_id', '=', ClassBookingStatus::BOOKED->value)
                    ->orWhere('class_bookings.class_booking_status_id', '=', ClassBookingStatus::NO_SHOW->value);
            })
            ->exists();
    }
}
