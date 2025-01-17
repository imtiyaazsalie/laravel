<?php

namespace App\Console\Commands\LeadMemberMigration;

use App\Models\ClassBooking;
use Illuminate\Console\Command;

class UpdateLeadClassBookingWithUserIdCommand extends Command
{
    protected $signature = 'lead-member:update-lead-class-booking-with-user-id';

    protected $description = 'Command description';

    public function handle(): void
    {
        $this->updateLeadClassBookingWithUserId();
    }

    private function updateLeadClassBookingWithUserId(): void
    {
        $classBookings = ClassBooking::query()
            ->whereNotNull('lead_member_id')
            ->whereNull('user_id')
            ->get();

        $this->info('Update Class Booking Table with Lead user_id');
        $progress = $this->output->createProgressBar(count($classBookings));
        $progress->start();

        foreach ($classBookings as $classBooking) {
            if ($classBooking->leadMember) {
                $classBooking->update(['user_id' => $classBooking->leadMember->user_id]);
                $progress->advance();
            }
        }

        $progress->finish();
    }
}
