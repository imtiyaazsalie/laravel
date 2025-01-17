<?php

namespace App\Jobs;

use App\Models\ClassRecurringBooking;
use App\Services\ClassService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateRecurringBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly ClassRecurringBooking $classRecurringBooking)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        (new ClassService())->disableSendEmails()->ensureBookingsForRecurringBooking($this->classRecurringBooking);
    }
}
