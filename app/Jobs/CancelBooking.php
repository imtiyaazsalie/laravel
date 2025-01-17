<?php

namespace App\Jobs;

use App\Models\ClassBooking;
use App\Models\TenantUser;
use App\Services\ClassBookingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class CancelBooking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public string $classBookingId,
        public string|int $actionedByMembershipId,
        public bool $isLateCancellation,
        public bool $deleteBooking = false
    ) {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ClassBookingsService $bookings)
    {
        $bookings->cancel(
            booking: ClassBooking::findOrFail($this->classBookingId),
            actionedBy: TenantUser::findOrFail($this->actionedByMembershipId),
            isLateCancellation: $this->isLateCancellation,
            deleteBooking: $this->deleteBooking
        );
    }
}
