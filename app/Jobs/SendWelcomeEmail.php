<?php

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\CrmService;
use App\Services\TenantUserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string|int $tenantId, public array $users)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);

        foreach ($this->users as $user) {

            $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($user, $tenant);

            if (! $userTenant instanceof TenantUser) {
                continue;
            }

            if ($userTenant->status == UserStatus::ACTIVE || $userTenant->status == UserStatus::PENDING) {

                (new CrmService())->sendPasswordResetWelcomeMail(user: $userTenant->user, tenant: $userTenant->tenant, location: $userTenant->tenantUserLocation?->location);
            }

        }
    }
}
