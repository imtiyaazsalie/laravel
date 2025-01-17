<?php

namespace App\Observers;

use App\Events\Users\UserUpdated;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class UserObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        //
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged(['name', 'surname', 'email'])) {
            TenantUser::query()
                ->active()
                ->where('user_id', $user->getAuthIdentifier())
                ->each(fn ($tenantUser) => event(
                    new UserUpdated(
                        user_id: $tenantUser->user_id,
                        tenant_id: $tenantUser->tenant_id,
                        type: $tenantUser->type,
                    )
                ));
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
