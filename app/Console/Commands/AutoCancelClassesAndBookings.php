<?php

namespace App\Console\Commands;

use App\Jobs\AutoCancelClassesAndBookingsJob;
use Illuminate\Console\Command;

class AutoCancelClassesAndBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:auto-cancel-classes-and-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This commands cancels all class and bookings that have auto cancellation setup for classes';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        AutoCancelClassesAndBookingsJob::dispatch()->onQueue('bookings');
    }
}
