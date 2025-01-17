<?php

namespace App\Console\Commands;

use App\Enums\ScheduleUserAction as ScheduleUserActionEnum;
use App\Enums\ScheduleUserActionStatus;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Models\ScheduleUserAction;
use App\Services\DebitBatchService;
use App\Services\ScheduledUserActionsService;
use App\Services\TenantUserService;
use App\Services\UserOnHoldService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RunScheduledUserActions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-scheduled-actions {--last-debit-date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled user actions.';

    public function handle(ScheduledUserActionsService $scheduledUserActionsService, DebitBatchService $debitBatchService, TenantUserService $tenantUserService, UserOnHoldService $userOnHoldService): int
    {
        ScheduleUserAction::query()
            ->where('status', ScheduleUserActionStatus::PENDING)
            ->where('failed_attempts', '<=', 3)
            ->when(
                $this->option('last-debit-date'),
                function (Builder $query) {
                    $query->join('user_to_box', function ($join) {
                        $join->on('scheduled_user_actions.user_id', '=', 'user_to_box.user_id')
                            ->on('scheduled_user_actions.box_id', '=', 'user_to_box.box_id');
                    })
                        ->where('scheduled_user_actions.last_debit_date', '<=', today()->toDateString())
                        ->where('scheduled_user_actions.action', ScheduleUserActionEnum::DEACTIVATE)
                        ->where('user_to_box.user_debit_status_id', UserDebitStatus::DEBIT_ORDER);
                },
                function (Builder $query) {
                    $query->where('date', '<=', today()->toDateString());
                }
            )
            ->each(function (ScheduleUserAction $scheduledUserAction) use ($debitBatchService, $tenantUserService, $userOnHoldService) {
                $tenantUser = $scheduledUserAction->userTenant;
                $user = $scheduledUserAction->user;

                if ($tenantUser->trashed() || $user->trashed()) {
                    return;
                }

                if ($this->option('last-debit-date')) {
                    $debitBatchService->deactivateFutureUserBatchesForUserBoxMembership($tenantUser);
                    $tenantUser->update(['user_debit_status_id' => UserDebitStatus::NO_PAYMENT]);
                    $tenantUserService->deactivateBankAccount($tenantUser);
                } else {
                    if ($scheduledUserAction->action === ScheduleUserActionEnum::PLACE_ON_HOLD) {

                        $userOnHoldService->placeUserOnHold(
                            tenantId: $scheduledUserAction->tenant_id,
                            user: $scheduledUserAction->user,
                            startDate: $scheduledUserAction->date,
                            releaseDate: $scheduledUserAction->release_date,
                            proRataFee: $scheduledUserAction->on_hold_pro_rata_fee,
                            note: "Scheduled place on hold: $scheduledUserAction->on_hold_note",
                            isExtendPackageEndDate: $scheduledUserAction->is_extend_package_end_date
                        );

                    } elseif ($scheduledUserAction->action === ScheduleUserActionEnum::DEACTIVATE) {
                        $tenantUser->update([
                            'user_status_id' => UserStatus::DEACTIVATED,
                            'deactivated_on' => now(),
                        ]);

                        // Clean up. Remove debit batches, class bookings, mailers, etc
                        $tenantUserService->deactivateUserCleanUp($tenantUser);
                    }

                    $scheduledUserAction->update([
                        'status' => ScheduleUserActionStatus::COMPLETE,
                    ]);
                }
            });

        return Command::SUCCESS;
    }
}
