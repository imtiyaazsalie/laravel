<?php

namespace App\Traits;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\LocationAccessPrivilege;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AccessPrivilegeService;
use App\Services\TenantUserService;
use Exception;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait Authorize
{
    public function canOperate(
        ?User $user = null,
        array|UserType|null $userTypes = null,
        ?string $permission = null,
        $tenantId = null,
        $locationId = null,
        $allowMember = false,
        $userId = null,
        ?array $userIdStatuses = null,
        bool $allowLocationCheckIn = false,
        ?string $scope = null,
        array $permissions = [],
    ): bool|Response {

        if ($permission && ! empty($permissions)) {
            $ex = (new Exception('Endpoint permissions not configured.'));
            report($ex);

            return Response::deny('An error occurred.');
        }

        if (! $user) {
            $user = auth()->user();

            if (! $user) {
                $ex = (new Exception('Middleware allows guest to hit canOperate.'));
                report($ex);

                return Response::deny('Access denied. Not logged in.');
            }
        }

        if (empty($userTypes)) {
            $ex = (new Exception('Endpoint authentication not configured.'));
            report($ex);

            return Response::deny('An error occurred.');
        }

        $userTypes = ! is_array($userTypes) ? [$userTypes] : $userTypes;

        if ($tenantId && (int) $tenantId != $tenantId) {
            return Response::deny('Tenant ID must be an integer.');
        }

        if ($locationId && (int) $locationId != $locationId) {
            return Response::deny('Location ID must be an integer.');
        }

        if ($userId && (int) $userId != $userId) {
            return Response::deny('User ID must be an integer.');
        }

        $tenantId = (int) $tenantId;

        if ($tenantId) {
            Tenant::findOrFail($tenantId);
        }

        $locationId = (int) $locationId;

        $userId = (int) $userId;

        if (! $user) {
            $user = auth()->user();
        }

        if ($user->is_redacted) {
            if (is_null($scope) || ! $user->tokenCan($scope)) {
                return Response::deny('Access token requires more scope.');
            }
        }

        if ($locationId && $tenantId) {
            $location = Location::findOrFail($locationId);

            if ($location->tenant_id !== $tenantId) {
                return Response::deny('Location ID does not match tenant ID.');
            }
        }

        $isOctivSuperAdmin = $user->user_type_id === UserType::SUPER_ADMINISTRATOR->value;
        $isOctivAdmin = $user->user_type_id === UserType::ADMIN->value;

        if ($isOctivSuperAdmin || $isOctivAdmin) {
            if (! in_array(UserType::from($user->user_type_id), $userTypes)) {
                return Response::deny('Super admin not allowed.');
            }

            return true;
        }

        if (! $tenantId) {
            if (! $userId) {
                $validator = Validator::make($this->input(), $this->rules(), [
                    'filter.user_id.required' => 'User ID is required without tenant ID.',
                    'user_id.required' => 'User ID is required without tenant ID.',
                ]);

                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }
            }

            if (! $this->canManageUser(user: $user, userId: $userId, userIdStatuses: $userIdStatuses)) {
                return Response::deny('You do not have access to this resource.');
            }

            return true;
        }

        $userTenant = $this->getUserTenant($user, $tenantId);

        if (! $userTenant) {
            return Response::deny('You do not have an account at this tenant.');
        }

        if (! in_array($userTenant->status, [UserStatus::ACTIVE, UserStatus::SUSPENDED, UserStatus::ON_HOLD])) {
            return Response::deny('Your account is not active at this tenant.');
        }

        $isOwner = $userTenant->user_type_id === UserType::HEAD_COACH;
        $isAdmin = $userTenant->user_type_id === UserType::BOX_ADMIN;
        $isLocationAdmin = $userTenant->user_type_id === UserType::BOX_FACILITY_ADMIN;
        $isLocationCheckIn = $userTenant->user_type_id === UserType::LOCATION_CHECK_IN;
        $isTrainer = $userTenant->user_type_id === UserType::GYM_COACH;
        $isMember = $userTenant->type === UserType::GYM_MEMBER;
        $isLead = $userTenant->user_type_id === UserType::LEAD_MEMBER;

        if (! in_array($userTenant->user_type_id, $userTypes)) {
            return Response::deny('User tenant not allowed.');
        }

        if ($isOwner) {
            if ($locationId && Location::query()->where('box_id', $tenantId)->where('box_facility_id', $locationId)->doesntExist()) {
                return Response::deny('Location does not belong to this tenant.');
            }

            if ($userId && ! $this->canManageUser(user: $user, userId: $userId, tenantId: $tenantId, userIdStatuses: $userIdStatuses)) {
                return Response::deny('Current user not able to manage this user.');
            }

            return true;
        }

        if ($isMember || $isLead) {

            if (! $allowMember) {
                return Response::deny('Authorized personnel only.');
            }

            if ($userId && $user->getAuthIdentifier() !== $userId) {
                return Response::deny('You may not access on this resource.');
            }

            return true;
        }

        if ($isAdmin || $isTrainer) {

            $userTenantLocationIds = LocationAccessPrivilege::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('revoked', false)
                ->get()
                ->pluck('box_facility_id')
                ->toArray();

            if ($locationId && ! in_array($locationId, $userTenantLocationIds)) {
                return Response::deny('Location access privilege not granted for current user.');
            }

            if ($permission && ! (new AccessPrivilegeService())->hasAccessToResource($userTenant, $permission)) {
                return Response::deny('Access privilege not granted for current user.');
            }

            if (! empty($permissions) && ! (new AccessPrivilegeService())->hasPermissions($permissions, $userTenant)) {
                return Response::deny('Access privilege not granted for current user.');
            }

            if ($userId && ! $this->canManageUser(user: $user, userId: $userId, tenantId: $tenantId, userIdStatuses: $userIdStatuses)) {
                return Response::deny('Current user not able to manage this user.');
            }

            return true;
        }

        if ($isLocationCheckIn) {
            if (! $allowLocationCheckIn) {
                return Response::deny('Location check-in user not allowed.');
            }

            return true;
        }

        if ($isLocationAdmin) {

            if ($locationId && (new TenantUserService())->getLocationUserByTenant($userTenant->user, $userTenant->tenant)?->location_id !== $locationId) {
                return Response::deny('Location access denied for current user.');
            }

            if ($permission && ! (new AccessPrivilegeService())->hasAccessToResource($userTenant, $permission)) {
                return Response::deny('Access privilege not granted for current user.');
            }

            if (! empty($permissions) && ! (new AccessPrivilegeService())->hasPermissions($permissions, $userTenant)) {
                return Response::deny('Access privilege not granted for current user.');
            }

            if ($userId && ! $this->canManageUser(user: $user, userId: $userId, tenantId: $tenantId, userIdStatuses: $userIdStatuses)) {
                return Response::deny('Current user not able to manage this user.');
            }

            return true;
        }

        return Response::deny('User type unknown.');
    }

    private function getUserTenant(User $user, $tenantId): ?TenantUser
    {
        return TenantUser::query()
            ->where('user_id', '=', $user->getAuthIdentifier())
            ->where('box_id', '=', $tenantId)
            ->whereIn('user_status_id', [UserStatus::ACTIVE, UserStatus::ON_HOLD, UserStatus::SUSPENDED])
            ->first();
    }

    private function canManageUser(User $user, int $userId, ?int $tenantId = null, ?array $userIdStatuses = null): bool|Response
    {
        if ($user->getKey() === $userId) {
            return true;
        }

        if (empty($userIdStatuses)) {
            $userIdStatuses = UserStatus::allStatuses();
        }

        //get common tenantUser records and match them to check rankings.
        $tenantUsers = TenantUser::query()
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('box_id', $tenantId);
            })
            ->where(function ($query) use ($user, $userId) {
                $query->where('user_id', $user->getAuthIdentifier())
                    ->orWhere('user_id', $userId);
            })->get();

        if (! $tenantId) {

            $authUserTenantIds = $tenantUsers->where('user_id', $user->getAuthIdentifier())->pluck('box_id')->toArray();

            $matchingTenantUser = $tenantUsers->where('user_id', $userId)->whereIn('box_id', $authUserTenantIds)->first();

            $tenantId = $matchingTenantUser?->box_id;

            if (! $tenantId) {
                return false;
            }
        }

        /**
         * If the userId being input is not of appropriate status
         */
        $userBeingOperatedOn = $tenantUsers
            ->where('box_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (is_null($userBeingOperatedOn) || ! in_array($userBeingOperatedOn->status, $userIdStatuses)) {
            return false;
        }

        //loop through current users memberships
        /** @var TenantUser $tenantUser */
        foreach ($tenantUsers->where('user_id', $user->getAuthIdentifier()) as $tenantUser) {

            //find matching memberships outranked by current membership
            if ($tenantUsers->where('box_id', $tenantUser->tenant_id)
                ->where('user_id', $userId)
                ->filter(fn ($otherTenantUser) => $this->matchOrOutrank($tenantUser, $otherTenantUser))
                ->count()
            ) {
                return true;
            }
        }

        return false;
    }

    private function matchOrOutrank(TenantUser $tenantUser, TenantUser $otherTenantUser): bool
    {
        // ranking

        // owner on all
        // admin on all except owner
        // location admin on all except admin and owner
        // trainer can operate on members only
        // location check in can only operate on themselves.
        // members can only operate on themselves.

        // user types match, staff may operate on each other (*qualified medical staff only)
        if ($tenantUser->type === $otherTenantUser->type) {
            return $tenantUser->isHeadCoach()
                || $tenantUser->isLocationAdmin()
                || $tenantUser->isTenantAdmin()
                || $tenantUser->isCoach();
        }

        if ($tenantUser->isHeadCoach()) {
            return true;
        }

        if ($tenantUser->isTenantAdmin()) {
            return ! $otherTenantUser->isHeadCoach();
        }

        if ($tenantUser->isLocationAdmin()) {
            return
                ! $otherTenantUser->isHeadCoach() &&
                ! $otherTenantUser->isTenantAdmin();
        }

        if ($tenantUser->isCoach()) {
            return
                ! $otherTenantUser->isHeadCoach() &&
                ! $otherTenantUser->isTenantAdmin() &&
                ! $otherTenantUser->isLocationAdmin();
        }

        return false;
    }
}
