<?php

namespace App\Services;

use App\Enums\MandateStatus;
use App\Enums\MandateType;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Mail\Mandate\Sepa\StatusChangeMail;
use App\Models\Location;
use App\Models\Mandate;
use App\Models\MandateGoCardless;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MandateService
{
    public function getAllMandatesForUserAndTenant(User|int $user, Tenant|int $tenant, MandateType $type)
    {
        return $this->getAllMandatesBaseQuery($user, $tenant, $type)->get();
    }

    public function getLatestMandate(User|int $user, Tenant|int $tenant, MandateType $typeId): ?Mandate
    {
        return $this->getAllMandatesBaseQuery($user, $tenant, $typeId)->first();
    }

    public function getLatestMandateByStatus(User|int $user, Tenant|int $tenant, MandateStatus $status, MandateType $type): ?Mandate
    {
        $mandate = $this->getLatestMandate($user, $tenant, $type);

        if ($mandate instanceof Mandate && $mandate->status !== $status) {
            $mandate = null;
        }

        return $mandate;
    }

    public function store($tenantId, $userId): Mandate
    {
        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenantId);

        abort_if(
            ! $tenantUser instanceof TenantUser,
            Response::HTTP_NOT_FOUND,
            'User does not have an active membership at this facility.'
        );

        abort_if(
            $tenantUser->user_debit_status_id !== UserDebitStatus::DEBIT_ORDER,
            Response::HTTP_BAD_REQUEST,
            'Please make sure that user is a debit order member.',
        );

        $mandate = $this->getLatestMandate($userId, $tenantId, MandateType::SEPA);

        abort_if(
            $mandate instanceof Mandate && in_array($mandate->status, [MandateStatus::ACTIVE, MandateStatus::CREATED]),
            Response::HTTP_BAD_REQUEST,
            'A mandate already exists for this user.',
        );

        return Mandate::create([
            'user_id' => $userId,
            'box_id' => $tenantId,
            'type' => MandateType::SEPA,
            'reference' => (new UtilityService())->generateNanoId(30),
            'status' => MandateStatus::CREATED,
        ]);
    }

    public function update(Mandate $mandate, MandateStatus $status): Mandate
    {
        abort_if(
            auth()->user()->getAuthIdentifier() !== $mandate->user_id && $status === MandateStatus::ACTIVE,
            Response::HTTP_BAD_REQUEST,
            'You cannot sign a mandate on behalf of another user.'
        );

        $mandate->update([
            'status' => $status,
        ]);

        if ($status === MandateStatus::ACTIVE) {
            $mandate->update([
                'signed_at' => now(),
                'cancelled_at' => null,
                'ip_address' => request()->ip(),
            ]);
        } elseif ($status === MandateStatus::CANCELLED) {
            $mandate->update([
                'cancelled_at' => now(),
            ]);
        }

        $this->sendSepaMail($mandate, $status);

        return $mandate;
    }

    public function sendOnboardingLinkToListOfUsers(int $tenantId, array $userIds): void
    {
        $tenantUserService = resolve(TenantUserService::class);

        foreach ($userIds as $userId) {
            $tenantUser = $tenantUserService->getCurrentUserTenantForTenant($userId, $tenantId);

            if (! $tenantUser instanceof TenantUser) {
                continue;
            }

            if ($tenantUser->type === UserType::SUPER_ADMINISTRATOR) {
                continue;
            }

            if ($tenantId !== $tenantUser->tenant_id) {
                continue;
            }

            $mandate = $this->getLatestMandate($userId, $tenantId, MandateType::SEPA);

            if ($mandate instanceof Mandate && $mandate->status === MandateStatus::ACTIVE) {
                continue;
            }

            if (! $mandate instanceof Mandate || $mandate->status === MandateStatus::CANCELLED) {
                Mandate::create([
                    'user_id' => $userId,
                    'box_id' => $tenantId,
                    'type' => MandateType::SEPA,
                    'reference' => (new UtilityService())->generateNanoId(30),
                    'status' => MandateStatus::CREATED,
                    'sent_at' => now(),
                ]);
            } else {
                $mandate->update(['sent_at' => now()]);
            }

            $this->sendOnboardingMail($tenantUser->user, $tenantUser->tenant, true);
        }
    }

    public function sendOnboardingMail(User $user, Tenant $tenant, ?bool $isSepa = false): void
    {
        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($user, $tenant);
        $locationUser = (new TenantUserService())->getLocationUserByTenant($user, $tenant);
        $location = $locationUser?->location;

        $crm = resolve(CrmService::class);

        try {
            if ($isSepa) {
                $content = Markdown::parse(
                    view('emails.mandate.sepa.onboarding', [
                        'memberName' => $user->full_name,
                        'url' => str(config('octiv.web_app_url'))->append('/sign/mandate')->toString(),
                    ])->render()
                )->__toString();
            } else {
                $content = Markdown::parse(
                    view('emails.gocardless.onboarding-link', [
                        'memberName' => $user->full_name,
                        'link' => str(config('octiv.web_app_url'))
                            ->append('/payment/gocardless-mandate/', $tenantUser->user_id)
                            ->append('/', $tenantUser->tenant_id),
                    ])->render()
                )->__toString();
            }

            $crm->createScheduledEmail(
                content: $content,
                subject: 'Octiv - Mandate Activation',
                to: $user->email,
                replyTo: $crm->getReplyTo($location ?? $tenant),
                tenant: $tenant,
                location: $location,
                queue: 'high'
            );
        } catch (\ReflectionException $e) {
            Log::error($e->getMessage());
        }
    }

    private function sendSepaMail(Mandate $mandate, MandateStatus $status): void
    {
        $locationUser = (new TenantUserService())->getLocationUserByTenant($mandate->user_id, $mandate->tenant_id);
        $location = $locationUser?->location;

        $admins = (new TenantUserService())->getTenantUsersBy($mandate->tenant, [UserType::HEAD_COACH, UserType::BOX_ADMIN], null, [UserStatus::ACTIVE]);

        $crm = resolve(CrmService::class);

        foreach ($admins as $admin) {
            $content = (new StatusChangeMail(
                $status,
                $admin->user->full_name,
                $mandate->user->full_name,
            ))->render();

            $crm->createScheduledEmail(
                content: $content,
                subject: 'Octiv - Mandate '.($status === MandateStatus::ACTIVE ? 'Activated' : 'Cancelled'),
                to: $admin->user->email,
                replyTo: $crm->getReplyTo($location ?? $mandate->tenant),
                signature: $crm->getEmailSignatureContent($location ?? $mandate->tenant),
                tenant: $mandate->tenant,
                location: $locationUser?->location,
                queue: 'high'
            );
        }
    }

    private function getAllMandatesBaseQuery(User|int $user, Tenant|int $tenant, MandateType $typeId)
    {
        return Mandate::query()
            ->where('user_id', $user instanceof User ? $user->getKey() : $user)
            ->where('box_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant)
            ->where('type', $typeId)
            ->orderBy('created_at', 'desc');
    }

    public function getMandateForUserAndLocation(User $user, Location $boxFacility, ?array $statuses = null): ?MandateGoCardless
    {
        return MandateGoCardless::query()
            ->from('go_cardless_mandates', 'mandate')
            ->join('box_facility_to_payment_gateway as gateway', 'gateway.facility_to_payment_gateway_id', '=', 'mandate.facility_payment_gateway_id')
            ->join('user_to_box as userBoxMembership', function ($join) {
                $join->on('mandate.user_id', '=', 'userBoxMembership.user_id')
                    ->on('mandate.box_id', '=', 'userBoxMembership.box_id');
            })
            ->where('gateway.box_facility_id', $boxFacility->getKey())
            ->where('mandate.user_id', $user->getKey())
            ->orWhere('userBoxMembership.user_id', $user->getKey())
            ->when($statuses, function ($query) use ($statuses) {
                return $query->whereIn('mandate.status', $statuses);
            })
            ->first();
    }
}
