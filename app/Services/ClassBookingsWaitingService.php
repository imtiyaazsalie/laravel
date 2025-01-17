<?php

namespace App\Services;

use App\Exceptions\Class\AttendanceLimitReachedException;
use App\Jobs\BookClass;
use App\Models\ClassBookingWaitingList;
use App\Models\ClassDate;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ClassBookingsWaitingService
{
    public function __construct(
        //public ClassBookingsService $bookings
    ) {
        // code...
    }

    /**
     * Process class booking waiting list.
     *
     *
     * @return void
     *
     * @throws AttendanceLimitReachedException
     */
    public function process(ClassDate $classDate, ?int $processLimit = null)
    {
        /**
         * Check that we have a waiting list
         */
        if (! $waitingList = $this->getWaitingList($classDate)) {
            return;
        }

        $classDate->loadMissing(['class.tenant', 'class.location']);

        /**
         * Check if class if open for booking
         */
        if (! $this->bookings->isClassOpenForBooking($classDate)) {
            return;
        }

        $spotsAvailable = $this->bookings->ensureAttendanceLimitNotReached($classDate);

        if (! $processLimit) {
            $processLimit = $spotsAvailable;
        }

        /**
         * Fetch waiting list user bookings and membership details to see if they can make a booking
         */
        $existingBookings = $this->bookings->getUserBookings(
            userIds: $waitingList->pluck('user_id')->toArray(),
            date: $classDate->class_date
        );

        /**
         * Get memberships for waiting list users that are eligible for booking this class
         */
        $tenantUsers = $this->bookings->getMembershipQueryForClassBookings($classDate)
            ->whereIn('user_id', $waitingList->pluck('user_id')->toArray())
            ->get();

        /**
         * Make bookings or mark for cancellation.
         */
        $waitingListCancellations = [];

        $waitingList->each(function ($waiting) use (&$waitingListCancellations, &$processLimit, $tenantUsers, $classDate) {
            if ($processLimit < 1) {
                return;
            }

            BookClass::dispatch(
                $classDate->getKey(),
                $tenantUsers->where('user_id', $waiting->user_id)->first()->getKey(),
                $waiting->created_by_id,
                $waiting
            )->onQueue('bookings')->afterCommit();

            $processLimit--;

        });

    }

    public function hasWaitingList(ClassDate $classDate): bool
    {
        return ClassBookingWaitingList::query()
            ->whereStatus('waiting')
            ->whereClassId($classDate->class_id)
            ->whereClassToDateId($classDate->getKey())
            ->orderBy('class_booking_waiting_id')
            ->exists();
    }

    /**
     * Undocumented function
     *
     * @return Collection|ClassBookingWaitingList[]
     */
    public function getWaitingList(ClassDate $classDate, ?array $relations = null): ?Collection
    {
        $query = ClassBookingWaitingList::query()
            ->whereStatus('waiting')
            ->whereClassId($classDate->class_id)
            ->where('class_to_date_id', '=', $classDate->getKey())
            ->orderBy('class_booking_waiting_id');

        if ($relations) {
            $query = $query->with($relations);
        }

        return $query->get();
    }

    public function getWaitingForClassAndDate(Classes $class, ClassDate $classDate): Collection|array
    {
        return ClassBookingWaitingList::query()
            ->where('class_id', '=', $class->getKey())
            ->where('status', '=', 'waiting')
            ->where('class_to_date_id', '=', $classDate->getKey())
            ->orderBy('class_to_date_id')
            ->get();

    }

    public function getWaitingForClassAndDateAndUser(Classes $class, ClassDate $classDate, User $user): ?Model
    {
        return ClassBookingWaitingList::query()
            ->where('class_id', '=', $class->getKey())
            ->where('status', '=', 'waiting')
            ->where('class_to_date_id', '=', $classDate->getKey())
            ->where('user_id', '=', $user->getKey())
            ->orderBy('class_to_date_id')
            ->first();
    }

    public function getNextClassBookingWaitingForClassDate(ClassDate $classDate)
    {
        return ClassBookingWaitingList::query()
            ->where('class_id', '=', $classDate->class_id)
            ->where('status', '=', 'waiting')
            ->where('class_to_date_id', '=', $classDate->getKey())
            ->orderBy('class_to_date_id')
            ->first();
    }

    public function getBookingWaitingForAthleteAndClassDate(User $athlete, ClassDate $classDate): ?object
    {
        return ClassBookingWaitingList::query()
            ->where('user_id', '=', $athlete->getKey())
            ->where('status', '=', 'waiting')
            ->where('class_to_date_id', '=', $classDate->getKey())
            ->orderBy('class_to_date_id')
            ->first();

    }
}
