<?php

namespace App\Console\Commands\CRM;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\TenantUser;
use App\Services\CrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DispatchSignUpNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-sign-up-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch CRM sign up notifications.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $crm = resolve(CrmService::class);

        $dates = [
            today()->subWeeks(2)->toDateString() => 'sign_up_two_weeks',
            today()->subMonthNoOverflow()->toDateString() => 'sign_up_one_month',
            today()->subMonthsNoOverflow(3)->toDateString() => 'sign_up_three_months',
            today()->subMonthsNoOverflow(6)->toDateString() => 'sign_up_six_months',
            today()->subYearNoOverflow()->toDateString() => 'sign_up_one_year',
        ];

        TenantUser::query()
            ->whereIn(DB::raw('DATE(created_on)'), array_keys($dates))
            ->where('user_status_id', UserStatus::ACTIVE)
            ->where('user_type_id', UserType::GYM_MEMBER)
            ->where('user_to_box.end_date', '>=', today()->toDateString())
            ->whereHas('locations', function ($query) {
                $query->where('box_facility.box_id', DB::raw('`user_to_box`.`box_id`'));
            })
            ->whereHas('tenantNotifications', function ($query) use ($dates) {
                $query->where('status', 'enabled')
                    ->whereIn('system_context', array_values($dates));
            })
            ->with('tenantNotifications', function ($query) use ($dates) {
                $query->where('status', 'enabled')
                    ->whereIn('system_context', array_values($dates));
            })
            ->chunk(500, function ($memberships) use ($dates, $crm) {
                foreach ($memberships as $tenantUser) {
                    if (! array_key_exists($tenantUser->created_on->toDateString(), $dates)) {
                        throw new RuntimeException('Should not get results for memberships created on dates that dont exist in the array.');
                    }

                    if ($context = Arr::get($dates, $tenantUser->created_on->toDateString())) {
                        $crm->createScheduledEmailForNotification(
                            tenantOrLocation: $tenantUser->tenant,
                            context: $context,
                            recipient: $tenantUser->user,
                            data: [
                                'member_name' => $tenantUser->user->full_name,
                                'user_name' => $tenantUser->user->name,
                                'user_surname' => $tenantUser->user->surname,
                                'location_name' => $tenantUser->locations()->where('box_id', $tenantUser->tenant_id)->first()->name,
                            ]
                        );
                    }
                }
            });

        return Command::SUCCESS;
    }
}
