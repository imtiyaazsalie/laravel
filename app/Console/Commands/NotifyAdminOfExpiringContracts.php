<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserContract;
use App\Services\CrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyAdminOfExpiringContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:dispatch-expiring-contract-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch contract expiry notifications to first head coach, box admins and gym members.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $crmService = resolve(CrmService::class);

        UserContract::query()
            ->select('user_contracts.*')
            ->join('user_to_box', 'user_contracts.user_id', '=', 'user_to_box.user_id')
            ->join('users', 'user_contracts.user_id', '=', 'users.user_id')
            ->join('user_to_facility', 'users.user_id', '=', 'user_to_facility.user_id')
            ->join('boxes', 'user_to_box.box_id', '=', 'boxes.box_id')
            ->join('box_facility', 'user_to_facility.box_facility_id', '=', 'box_facility.box_facility_id')
            ->where('boxes.box_status_id', '=', TenantStatus::ACTIVE)
            ->whereDate('user_contracts.ending_on', DB::raw('DATE_ADD(CURDATE(), INTERVAL boxes.contract_expiry_notfication_days DAY)'))
            ->whereDate('user_to_box.end_date', '>', today())
            ->whereDate('user_to_facility.end_date', '>', today())
            ->whereNotIn('user_to_box.user_status_id', [UserStatus::PENDING->value, UserStatus::DEACTIVATED->value])
            ->whereRaw('user_to_box.box_id = user_contracts.box_id')
            ->whereRaw('boxes.box_id = box_facility.box_id')
            ->where('users.deleted', '=', 0)
            ->where('user_to_box.deleted', '=', 0)
            ->distinct()
            ->chunk(500, function ($userContracts) use ($crmService) {
                /** @var UserContract $userContract */
                foreach ($userContracts as $userContract) {
                    // Check if user has a newer active contract. If yes then don't send notification out.
                    // If the expired contract is not the same as the latest user contract then next check if the contract expiry date is not the same as the expired contract
                    $latestContract = $userContract->user->latestContract($userContract->tenant)->first();

                    if ($latestContract) {
                        if (($userContract->getKey() !== $latestContract->getKey()) && ($userContract->ending_on !== $latestContract->ending_on)) {
                            continue;
                        }
                    }

                    $headCoaches = TenantUser::query()
                        ->active()
                        ->headCoaches()
                        ->where('box_id', $userContract->tenant_id)
                        ->whereHas('user', fn ($query) => $query->where('deleted', 0))
                        ->get();

                    if ($headCoaches->isNotEmpty()) {
                        $locationAdmins = TenantUser::query()
                            ->active()
                            ->where('user_type_id', UserType::BOX_FACILITY_ADMIN)
                            ->where('box_id', $userContract->tenant_id)
                            ->whereHas('userLocation', function ($query) use ($userContract) {
                                $query->active()
                                    ->where('box_facility_id', $userContract->location_id);
                            })
                            ->get()
                            ->pluck('user.email')
                            ->toArray();

                        $primaryRecipient = $headCoaches->first()->user;

                        $headCoachEmails = $headCoaches
                            ->filter(fn ($headCoach) => $headCoach->user->user_id !== $primaryRecipient->user_id)
                            ->pluck('user.email')
                            ->toArray();

                        $ccRecipients = array_merge($headCoachEmails, $locationAdmins);

                        $crmService->createScheduledEmailForNotification(
                            tenantOrLocation: $userContract->tenant,
                            context: 'contract_expiry_noitification', // Notification context: the typo in DB
                            recipient: $primaryRecipient,
                            cc: $ccRecipients,
                            data: [
                                'coach_name' => $primaryRecipient->name,
                                'coach_surname' => $primaryRecipient->surname,
                                'member_name' => $userContract->user->name,
                                'member_surname' => $userContract->user->surname,
                                'member_email' => $userContract->user->email,
                                'member_mobile' => $userContract->user->mobile,
                                'expiry_date' => $userContract->ending_on->format('D d F, Y'),
                                'expiry_notification_days' => $userContract->tenant->contract_expiry_notfication_days,
                            ]
                        );
                    }

                    $crmService->createScheduledEmailForNotification(
                        tenantOrLocation: $userContract->location,
                        context: 'contract_expiry_member_notification',
                        recipient: $userContract->user,
                        data: [
                            'member_name' => $userContract->user->name,
                            'member_surname' => $userContract->user->surname,
                            'member_email' => $userContract->user->email,
                            'member_mobile' => $userContract->user->mobile,
                            'expiry_date' => $userContract->ending_on->format('D d F, Y'),
                            'expiry_notification_days' => $userContract->tenant->contract_expiry_notfication_days,
                        ]
                    );
                }
            });

        return Command::SUCCESS;
    }
}
