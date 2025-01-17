<?php

namespace App\Services;

use App\Enums\ClassBookingStatus;
use App\Jobs\BookClass;
use App\Jobs\CancelBooking;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\ClassRecurringBooking;
use App\Models\TenantUser;
use Carbon\Carbon;

class RecurringBookingService
{
    public function queueClassBookings(ClassRecurringBooking $recurringBooking, TenantUser $tenantUser)
    {
        $recurringBooking->loadMissing(['daysOfWeek']);

        $existingClassDateIds = ClassBooking::query()
            ->select(['class_booking_id', 'class_to_date_id'])
            ->whereClassId($recurringBooking->class_id)
            ->whereUserId($tenantUser->user_id)
            ->get()
            ->pluck('class_to_date_id')
            ->toArray();

        $classDates = ClassDate::query()
            ->whereIsActive(true)
            ->whereClassId($recurringBooking->class_id)
            ->whereNotIn('class_to_date_id', $existingClassDateIds)
            ->whereDate('class_date', '>=', now());

        if ($recurringBooking->dt_deactivate) {
            $classDates = $classDates->whereDate('class_date', '<=', $recurringBooking->dt_deactivate);
        }

        $classDates->get()->each(function ($classDate) use ($recurringBooking, $tenantUser) {
            if ($recurringBooking->daysOfWeek->contains($classDate->day_of_week->value)) {
                BookClass::dispatch($classDate->getKey(), $tenantUser->getKey())->onQueue('bookings')->afterCommit();
            }
        });
    }

    public function queueBookingCancellations(ClassRecurringBooking $recurringBooking, string|int $actionedByMembershipId, Carbon $from, bool $deleteBooking = false)
    {
        ClassBooking::query()
            ->whereRelation('classDate', 'class_date', '>=', $from->format('Y-m-d'))
            ->whereClassId($recurringBooking->class->getKey())
            ->whereUserId($recurringBooking->user_id)
            ->whereClassBookingStatusId(ClassBookingStatus::BOOKED->value)
            ->each(function ($classBooking) use ($deleteBooking, $actionedByMembershipId) {
                CancelBooking::dispatch(
                    classBookingId: $classBooking->getKey(),
                    actionedByMembershipId: $actionedByMembershipId,
                    isLateCancellation: false,
                    deleteBooking: $deleteBooking,
                );
            });
    }
}
