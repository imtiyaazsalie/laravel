<?php

namespace App\Console\Commands\CRM;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Services\CrmService;
use Illuminate\Console\Command;

class SendBirthdayNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-birthday-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send birthday reminders to coach and wishes to the user.';

    /**
     * Execute the console command.
     */
    public function handle(CrmService $settings): int
    {
        // Get active boxes that have a head coach, send birthday wishes, along with their active gym members who have birthdays today
        User::query()
            ->select('users.*')
            ->distinct()
            ->join('user_to_box', 'users.user_id', '=', 'user_to_box.user_id')
            ->whereRaw("CONCAT(MONTH(users.dob), '-', DAY(users.dob)) = '".today()->format('n-j')."'")
            ->where('user_to_box.user_type_id', UserType::GYM_MEMBER->value)
            ->where('user_to_box.user_status_id', UserStatus::ACTIVE->value)
            ->whereDate('user_to_box.end_date', '>=', today())
            ->with('tenantUser.tenant')
            ->chunk(100, function ($users) use ($settings) {
                foreach ($users as $user) {
                    foreach ($user->tenantUser as $tenantUser) {

                        if ($tenantUser->status != UserStatus::ACTIVE) {
                            continue;
                        }

                        if ($tenantUser->type != UserType::GYM_MEMBER) {
                            continue;
                        }

                        $headCoach = $tenantUser->tenant
                            ->headCoaches()
                            ->where('user_to_box.end_date', '>', today()->toDateString())
                            ->where('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED)
                            ->first();

                        if (! $headCoach) {
                            continue;
                        }

                        $settings->createScheduledEmailForNotification(
                            tenantOrLocation: $tenantUser->tenant,
                            context: 'birthday_reminder',
                            recipient: $headCoach,
                            data: [
                                'member_name' => $user->name,
                                'member_surname' => $user->surname,
                                'coach_name' => $headCoach->name,
                                'coach_surname' => $headCoach->surname,
                            ]
                        );

                        $settings->createScheduledEmailForNotification(
                            tenantOrLocation: $tenantUser->tenant,
                            context: 'birthday',
                            recipient: $user,
                            data: [
                                'member_name' => $user->name,
                            ]
                        );
                    }
                }
            });

        return Command::SUCCESS;
    }
}
