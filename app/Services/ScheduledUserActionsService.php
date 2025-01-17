<?php

namespace App\Services;

use App\Enums\ScheduleUserActionStatus;
use App\Enums\UserDebitStatus;
use App\Models\ScheduleUserAction;
use App\Models\TenantUser;
use Illuminate\Support\Carbon;

class ScheduledUserActionsService
{
    public function createScheduleAction(TenantUser $userBoxMembership, string $action, Carbon $actionDate, ?Carbon $releaseDate = null, ?float $proRataFee = null, ?string $note = null, ?bool $isExtendPackageEndDate = false, ?bool $excludeFromUpcomingDebitOrder = false, ?Carbon $lastDebitDate = null): void
    {
        $scheduleUserAction = new ScheduleUserAction();

        $scheduleUserAction->setAttribute('user_id', $userBoxMembership->user_id);
        $scheduleUserAction->setAttribute('box_id', $userBoxMembership->box_id);
        $scheduleUserAction->setAttribute('action', $action);
        $scheduleUserAction->setAttribute('date', $actionDate);
        $scheduleUserAction->setAttribute('status', ScheduleUserActionStatus::PENDING);

        if ($action === \App\Enums\ScheduleUserAction::PLACE_ON_HOLD->value) {
            $today = date('Y-m-d @ H:i');
            $scheduleUserAction->setAttribute('on_hold_note', $note ?? 'Placed on hold by: '.auth()->user()->name." on $today");
            $scheduleUserAction->setAttribute('on_hold_pro_rata_fee', $proRataFee);
            $scheduleUserAction->setAttribute('on_hold_release_date', $releaseDate);
            $scheduleUserAction->setAttribute('extend_package_end_date', $isExtendPackageEndDate);

        } elseif ($action === \App\Enums\ScheduleUserAction::DEACTIVATE->value) {
            if ($lastDebitDate instanceof Carbon) {
                $today = today();
                $lastDebitDate->setTime(0, 0);
                $scheduleUserAction->setAttribute('last_debit_date', $lastDebitDate);

                if ($lastDebitDate <= $today) {
                    (new DebitBatchService())->deactivateFutureUserBatchesForUserBoxMembership($userBoxMembership);

                    // Set user to no payment user
                    $userBoxMembership->setAttribute('user_debit_status_id', UserDebitStatus::NO_PAYMENT);

                    // Disable user's banking details
                    (new TenantUserService())->deactivateBankAccount($userBoxMembership);
                }
            } elseif ($excludeFromUpcomingDebitOrder) {
                $scheduleUserAction->setAttribute('exclude_from_future_batches', true);

                (new DebitBatchService())->deactivateFutureUserBatchesForUserBoxMembership($userBoxMembership);

                // Set user to no payment user
                $userBoxMembership->setAttribute('user_debit_status_id', UserDebitStatus::NO_PAYMENT);
                // Disable user's banking details
                (new TenantUserService())->deactivateBankAccount($userBoxMembership);
            }
        }
        $scheduleUserAction->save();
        $userBoxMembership->save();
    }
}
