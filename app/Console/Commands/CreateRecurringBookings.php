<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Jobs\CreateRecurringBookings as JobsCreateRecurringBookings;
use App\Models\ClassRecurringBooking;
use Illuminate\Console\Command;
use Illuminate\Database\Query\JoinClause;

class CreateRecurringBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:extend-recurring-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create recurring class bookings.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Deactivating appropriate recurring bookings before creating classes...');

        ClassRecurringBooking::query()
            ->where('dt_deactivate', '<=', today()->endOfDay()->toDateString())
            ->update([
                'active' => false,
                'dt_modified' => now(),
                'dt_deactivate' => now(),
            ]);

        $this->info('Fetching active recurring bookings...');

        ClassRecurringBooking::query()
            ->select('class_recurring_bookings.*')
            ->join('classes', 'class_recurring_bookings.class_id', '=', 'classes.class_id')
            ->join('users', 'class_recurring_bookings.user_id', '=', 'users.user_id')
            ->join('user_to_box', function (JoinClause $join) {
                $join->on('classes.box_id', '=', 'user_to_box.box_id')
                    ->on('users.user_id', '=', 'user_to_box.user_id');
            })
            ->join('box_facility', 'classes.box_facility_id', '=', 'box_facility.box_facility_id')
            ->join('boxes', 'box_facility.box_id', '=', 'boxes.box_id')
            ->where('class_recurring_bookings.active', '=', true)
            ->where('user_to_box.user_status_id', '=', UserStatus::ACTIVE)
            ->whereDate('end_date', '>', now())
            ->where('classes.is_active', '=', true)
            ->where('box_facility.is_active', '=', true)
            ->where('boxes.box_status_id', '=', TenantStatus::ACTIVE)
            ->distinct()
            ->chunk(500, function ($classRecurringBookings) {
                foreach ($classRecurringBookings as $classRecurringBooking) {
                    $this->info("Processing class recurring booking ID: {$classRecurringBooking->getKey()}");

                    JobsCreateRecurringBookings::dispatch(classRecurringBooking: $classRecurringBooking);
                }
            });

        return Command::SUCCESS;
    }
}
