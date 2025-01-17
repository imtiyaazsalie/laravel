<?php

namespace App\Observers;

use App\Enums\UserType;
use App\Events\Users\LeadConverted;
use App\Events\Users\UserCreated;
use App\Events\Users\UserDeleted;
use App\Events\Users\UserStatusChanged;
use App\Models\TenantUser;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class TenantUserObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the TenantUser "created" event.
     */
    public function created(TenantUser $tenantUser): void
    {
        event(
            new UserCreated(
                tenant_id: $tenantUser->tenant_id,
                user_id: $tenantUser->user_id,
                type: $tenantUser->type,
            )
        );
    }

    /**
     * Handle the TenantUser "updated" event.
     */
    public function updated(TenantUser $tenantUser): void
    {
        if ($tenantUser->wasChanged('user_type_id')) {
            if ($tenantUser->getOriginal('user_type_id') === UserType::LEAD_MEMBER) {
                event(
                    new LeadConverted(
                        tenant_id: $tenantUser->tenant_id,
                        user_id: $tenantUser->user_id,
                    )
                );
            }
        }

        if ($tenantUser->wasChanged('user_status_id')) {
            event(
                new UserStatusChanged(
                    tenant_id: $tenantUser->tenant_id,
                    user_id: $tenantUser->user_id,
                    type: $tenantUser->type,
                    status: $tenantUser->status
                )
            );
        }

        if ($tenantUser->wasChanged('deleted') && $tenantUser->deleted) {
            event(
                new UserDeleted(
                    tenant_id: $tenantUser->tenant_id,
                    user_id: $tenantUser->user_id,
                    type: $tenantUser->type,
                )
            );
        }
    }

    /**
     * Handle the TenantUser "deleted" event.
     */
    public function deleted(TenantUser $tenantUser): void
    {

    }

    /**
     * Handle the TenantUser "restored" event.
     */
    public function restored(TenantUser $tenantUser): void
    {
        //
    }

    /**
     * Handle the TenantUser "force deleted" event.
     */
    public function forceDeleted(TenantUser $tenantUser): void
    {
        //
    }
}
