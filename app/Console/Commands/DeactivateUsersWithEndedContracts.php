<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\UserContract;
use App\Services\TenantUserService;
use Illuminate\Console\Command;
use Illuminate\Database\Query\JoinClause;

class DeactivateUsersWithEndedContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deactivate-users-with-expired-contracts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate users with contracts that are expiring.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantUserService = resolve(TenantUserService::class);

        UserContract::query()
            ->select('user_contracts.*')
            ->join('boxes', 'user_contracts.box_id', '=', 'boxes.box_id')
            ->join('users', 'user_contracts.user_id', '=', 'users.user_id')
            ->join('user_to_box', function (JoinClause $join) {
                $join->on('users.user_id', '=', 'user_to_box.user_id')
                    ->on('boxes.box_id', '=', 'user_to_box.box_id');
            })
            ->where('boxes.deactivate_contracts_ended', '=', true)
            ->whereNotNull('user_contracts.ending_on')
            ->whereDate('user_contracts.ending_on', today())
            ->where('boxes.box_status_id', '=', TenantStatus::ACTIVE)
            ->where('user_to_box.user_status_id', '=', UserStatus::ACTIVE)
            ->where('users.deleted', '=', false)
            ->where('user_to_box.deleted', '=', false)
            ->distinct()
            ->withoutGlobalScope('userTenant')
            ->chunk(500, function ($userContracts) use ($tenantUserService) {
                foreach ($userContracts as $userContract) {
                    $userContract->tenantUser->update([
                        'user_status_id' => UserStatus::DEACTIVATED,
                        'deactivated_on' => now(),
                        'updated_on' => now(),
                    ]);

                    $tenantUserService->deactivateUserCleanup($userContract->tenantUser);
                }
            });

        return Command::SUCCESS;
    }
}
