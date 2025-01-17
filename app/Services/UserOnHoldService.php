<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserOnHold;
use Illuminate\Support\Carbon;

class UserOnHoldService
{
    public function placeUserOnHold(
        string|int $tenantId,
        User $user,
        Carbon $startDate,
        ?Carbon $releaseDate,
        ?float $proRataFee,
        ?string $note,
        ?bool $isExtendPackageEndDate = false,
        ?User $actionedBy = null,
    ): UserOnHold {
        $userOnHold = UserOnHold::query()
            ->where('box_id', $tenantId)
            ->where('user_id', $user->getKey())
            ->first();

        if ($userOnHold) {
            return $userOnHold;
        }

        $actionedByNote = $actionedBy ? "Placed on hold by: $actionedBy->full_name on ".now()->format('Y-m-d @ H:i').'.' : null;

        $userOnHold = UserOnHold::create([
            'user_id' => $user->getKey(),
            'tenant_id' => $tenantId,
            'start_date' => $startDate,
            'release_date' => $releaseDate,
            'pro_rata_fee' => $proRataFee ?? 0,
            'note' => $note ?? $actionedByNote,
            'extend_package_end_date' => $isExtendPackageEndDate,
        ]);

        //extend package end dates
        if ($releaseDate && $isExtendPackageEndDate) {
            $this->extendPackagesEndDate($tenantId, $user, $startDate, $releaseDate);
        }

        /** @var ClassService $classService */
        $classService = resolve(ClassService::class);

        /** @var DebitBatchService $debitBatchService */
        $debitBatchService = resolve(DebitBatchService::class);

        // Remove all future debit batches
        $debitBatchService->deactivateFutureUserBatchesForUserBoxMembership($userOnHold->userTenant);

        // Delete all future recurring class bookings.
        $classService->removeClassRecurringBookingsAndClassBookingsForUser($userOnHold->userTenant);
        $classService->cancelFutureRecurringBookingsAfterUserScheduledDeactivation($userOnHold->userTenant);

        // Cancel all future class bookings
        $classService->cancelFutureBookingsForUser($userOnHold->userTenant);

        // Set user's status to on-hold
        $userOnHold->userTenant()->update([
            'user_status_id' => UserStatus::ON_HOLD,
        ]);

        return $userOnHold;
    }

    public function releaseOnHoldUser(UserOnHold $userOnHold, bool $isManualRelease = false, ?bool $isExtendPackageEndDateOverride = null): void
    {
        $isExtendPackageEndDate = $isExtendPackageEndDateOverride || $userOnHold->extend_package_end_date;

        if ($isManualRelease) {
            // If package was extended before revert to original dates
            if ($userOnHold->extend_package_end_date) {
                $this->revertExtendPackagesEndDate($userOnHold->tenant_id, $userOnHold->user, $userOnHold->start_date, $userOnHold->release_date);
            }

            // Extend the package end dates
            if ($isExtendPackageEndDate) {
                $this->extendPackagesEndDate($userOnHold->tenant_id, $userOnHold->user, $userOnHold->start_date, today());
            }
        }

        // Update on-hold record
        $userOnHold->update([
            'release_date' => now(),
        ]);

        $userOnHold->delete();

        $tenantUser = TenantUser::query()
            ->where('box_id', $userOnHold->tenant_id)
            ->where('user_id', $userOnHold->user_id)
            ->first();

        // Set user's status back to active
        $tenantUser->update(['user_status_id' => UserStatus::ACTIVE]);

        if ($tenantUser->isDebitOrder() && $tenantUser->bankAccount()->exists()) {
            (new DebitBatchService())->ensureOnHoldReleasedUserIsOnNextUnprocessedBatchWithAmount($userOnHold);
        }
    }

    public function revertExtendPackagesEndDate(string|int $tenantId, User $user, Carbon $startDate, ?Carbon $releaseDate): void
    {
        $interval = $startDate->diff($releaseDate);
        $revertByDays = $interval->format('P%aD');

        $user->userPackages()
            ->active($startDate)
            ->where('packages.box_id', $tenantId)
            ->whereNotNull('user_to_package.end_date')
            ->get()
            ->each(function ($userPackage) use ($revertByDays) {
                $userPackage->update([
                    'end_date' => $userPackage->end_date->sub($revertByDays),
                ]);
            });
    }

    public function extendPackagesEndDate(string|int $tenantId, User $user, Carbon $startDate, Carbon $releaseDate): void
    {
        $interval = $startDate->diff($releaseDate);
        $extendByDays = $interval->format('P%aD');

        $user->userPackages()
            ->active($startDate)
            ->where('packages.box_id', $tenantId)
            ->whereNotNull('user_to_package.end_date')
            ->get()
            ->each(function ($userPackage) use ($extendByDays) {
                $userPackage->update([
                    'end_date' => $userPackage->end_date->add($extendByDays),
                ]);
            });
    }

    public function isTenantUserOnHold(TenantUser $tenantUser): bool
    {
        $result = UserOnHold::query()
            ->withoutGlobalScope('userTenant')
            ->where('user_id', '=', $tenantUser->user_id)
            ->where('box_id', '=', $tenantUser->box_id)
            ->get();

        return $result->count() > 0;
    }
}
