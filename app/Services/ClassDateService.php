<?php

namespace App\Services;

use App\Enums\ClassBookingStatus;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\Classes;
use App\Models\LeadMember;
use App\Models\User;
use App\Services\CRM\DiscoveryNotificationsService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class ClassDateService
{
    public function getClassDatesByIdsForTenant(int $tenantId, $classDateIds): Collection
    {
        return ClassDate::query()
            ->join('classes', 'class_to_dates.class_id', '=', 'classes.class_id')
            ->where('classes.box_id', '=', $tenantId)
            ->whereIn('class_to_date_id', $classDateIds)
            ->where('class_to_dates.is_active', '=', true)
            ->get();
    }

    public function getClassDate(ClassDate $classDate): ?ClassDate
    {
        return QueryBuilder::for(ClassDate::class)
            ->join('classes as c', 'class_to_dates.class_id', '=', 'c.class_id')
            ->join('box_facility as bf', 'c.box_facility_id', '=', 'bf.box_facility_id')
            ->join('boxes as b', 'b.box_id', '=', 'bf.box_id')
            ->withCoachIds()
            ->groupBy('class_to_dates.class_to_date_id')
            ->addSelect('c.box_facility_id AS box_facility_id')
            ->addSelect(DB::raw('(SELECT COUNT(cb.class_booking_id) FROM class_bookings cb WHERE cb.class_to_date_id = class_to_dates.class_to_date_id AND cb.class_booking_status_id IN (1,5)) AS attendanceCount'))
            ->orderBy('class_to_dates.class_date')
            ->orderBy(DB::raw('IFNULL(class_to_dates.start_time, c.start_time)'))
            ->with(['tags', 'headCoach.userTenant', 'supportingCoach.userTenant', 'classBookings.user', 'classBookingWaitingList'])
            ->where('class_to_dates.class_to_date_id', $classDate->getKey())
            ->first();
    }

    public function getUpcomingClassDatesForClass(Classes $class, ?Carbon $fromDate = null): Collection|array
    {
        $fromDate = $fromDate ?? today();

        return ClassDate::query()
            ->join('classes', 'class_to_dates.class_id', '=', 'classes.class_id')
            ->where('class_to_dates.is_active', true)
            ->where('class_to_dates.class_id', $class->getKey())
            ->whereDate('class_to_dates.class_date', '>=', $fromDate->toDateString())
            ->orderBy('class_to_dates.class_date')
            ->orderBy('class_to_dates.start_time')
            ->get();
    }

    public function getUpcomingClassDatesByClass(Classes $class, ?Carbon $fromDate = null): Collection|array
    {
        $fromDate = $fromDate ?? today();

        return ClassDate::query()
            ->where('class_date', '>=', $fromDate->toDateString())
            ->where('class_id', $class->getKey())
            ->where('is_active', true)
            ->orderBy('class_date')
            ->get();
    }

    public function notifyClassBookingsOfClassDateChange(ClassDate $classDate, ?string $oldTimeText = null, ?string $newTimeText = null, ?string $classCoachText = null): void
    {
        $crmService = new CrmService();

        $class = $classDate->class;

        $classBookings = (new ClassBookingsService())->getBookingsForClassDate($classDate, [ClassBookingStatus::BOOKED]);

        /** @var ClassBooking $classBooking */
        foreach ($classBookings as $classBooking) {
            if ($classBooking->user instanceof User) {
                $recipient = $classBooking->user;
            } elseif ($classBooking->leadMember instanceof LeadMember) {
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
                        'class_name' => $class->name,
                        'class_old_time' => $oldTimeText,
                        'class_new_time' => $newTimeText,
                        'class_date' => $classDate->date->format('D, d-m-Y'),
                        'class_coach' => $classCoachText,
                    ],
                );
            }
        }
    }

    public function getClassDateForClassAndDate(int $classId, CarbonInterface $date): ?ClassDate
    {
        return ClassDate::query()
            ->where('class_id', '=', $classId)
            ->whereDate('class_date', '=', $date)
            ->first();
    }
}
