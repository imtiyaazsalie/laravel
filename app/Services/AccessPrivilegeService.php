<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\AccessPrivilege;
use App\Models\Location;
use App\Models\LocationAccessPrivilege;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserAccessPrivilege;
use Illuminate\Support\Arr;

class AccessPrivilegeService
{
    public function initializeUserPrivileges(TenantUser $tenantUser, UserType $type, ?bool $isAccessRevoked = false): void
    {
        $success = 0;
        $error = 0;
        $accessPrivileges = $this->getAccessPrivilegesByUserType($type);

        /** @var AccessPrivilege $accessPrivilege */
        foreach ($accessPrivileges as $accessPrivilege) {

            // Check if this user has access to this resource
            /** @var UserAccessPrivilege $userAccessPrivilege */
            $userAccessPrivilege = UserAccessPrivilege::query()
                ->where('user_id', $tenantUser->user_id)
                ->where('box_id', $tenantUser->tenant_id)
                ->where('access_privilege_id', $accessPrivilege->getKey())
                ->first();

            if (! $userAccessPrivilege) {
                $userAccessPrivilege = new UserAccessPrivilege();
                $userAccessPrivilege->setAttribute('user_id', $tenantUser->user_id);
                $userAccessPrivilege->setAttribute('box_id', $tenantUser->tenant_id);
                $userAccessPrivilege->setAttribute('access_privilege_id', $accessPrivilege->getKey());
                $userAccessPrivilege->setAttribute('revoked', false);

                if ($isAccessRevoked) {
                    $userAccessPrivilege->setAttribute('revoked', true);
                    $userAccessPrivilege->setAttribute('revoked_on', today());
                }

                $userAccessPrivilege->save();

                $success++;
            } else {
                $error++;
            }
        }
    }

    public function getAccessPrivilegesByUserType(UserType $userType)
    {
        return AccessPrivilege::query()
            ->from('access_privileges', 'ap')
            ->join('access_privileges_to_user_types as ut', 'ap.access_privilege_id', '=', 'ut.access_privilege_id')
            ->where('ut.user_type_id', $userType->value)
            ->orderBy('ap.category')
            ->orderBy('ap.name')
            ->_paginate();
    }

    public function initializeUserFacilityPrivileges(TenantUser $tenantUser, ?bool $isAccessRevoked = false)
    {
        $boxFacilities = Location::query()
            ->where('box_id', $tenantUser->box_id)
            ->get();

        /** @var Location $boxFacility */
        foreach ($boxFacilities as $boxFacility) {
            if ($boxFacility->is_active) {
                // Check if this user has access to this facility
                /** @var LocationAccessPrivilege $userFacilityAccessPrivilege */
                $userFacilityAccessPrivilege = LocationAccessPrivilege::query()
                    ->where('user_id', $tenantUser->user_id)
                    ->where('box_facility_id', $boxFacility->getKey())
                    ->first();

                if ($userFacilityAccessPrivilege instanceof LocationAccessPrivilege) {
                    continue;
                }

                $userFacilityAccessPrivilege = new LocationAccessPrivilege();
                $userFacilityAccessPrivilege->setAttribute('user_id', $tenantUser->user_id);
                $userFacilityAccessPrivilege->setAttribute('box_facility_id', $boxFacility->getKey());

                // If location admin or location check-in coach revoke access to all other facilities
                if (in_array($tenantUser->type, [UserType::BOX_FACILITY_ADMIN, UserType::LOCATION_CHECK_IN])
                    && (new TenantUserService)->getLocationUserByTenant($tenantUser->user, $tenantUser->tenant)->location_id !== $boxFacility->getKey()) {
                    $userFacilityAccessPrivilege->setAttribute('revoked', true);
                    $userFacilityAccessPrivilege->setAttribute('revoked_on', today());
                }

                if ($isAccessRevoked) {
                    $userFacilityAccessPrivilege->setAttribute('revoked', true);
                    $userFacilityAccessPrivilege->setAttribute('revoked_on', today());
                }

                $userFacilityAccessPrivilege->save();
            }
        }
    }

    public function getAllowedLocationIds(TenantUser $tenantUser): array
    {
        $allowedLocations = [];

        if ($tenantUser->type === UserType::HEAD_COACH) {
            $tenantUser->tenant->locations()->each(function (Location $location) use (&$allowedLocations) {
                $allowedLocations[] = $location->getKey();
            });

            return $allowedLocations;
        }

        $userLocationAccessPrivileges = LocationAccessPrivilege::query()
            ->where('user_id', '=', $tenantUser->user_id)
            ->where('revoked', '=', false)
            ->get();

        foreach ($userLocationAccessPrivileges as $userLocationAccessPrivilege) {
            $allowedLocations[] = $userLocationAccessPrivilege->location->getKey();
        }

        return $allowedLocations;
    }

    public function hasPermissions(
        array $allPermissions,
        TenantUser $userTenant,
    ): bool {
        $defaults = Arr::get($allPermissions, 'default');

        $permissions = Arr::get($allPermissions, $userTenant->type->value, $defaults);

        // if the developer hasn't set defaults or specific permissions for the user type,
        // then we assume they want to allow the request.
        if (! $permissions) {
            return true;
        }

        $permissions = (array) $permissions;

        $allPermissionsRequired = Arr::pull($permissions, 'allPermissionsRequired', false);

        //check permissions
        $hasAllPermission = true;

        foreach ($permissions as $permission) {

            if ($this->hasAccessToResource($userTenant, $permission)) {
                if ($allPermissionsRequired) {
                    continue;
                }

                // not all permissions are required, so the user has permissions
                return true;

            } else {

                if ($allPermissionsRequired) {
                    return false;
                }

                $hasAllPermission = false;
            }
        }

        return $hasAllPermission;
    }

    /**
     * This function checks if the current user has access to a certain resource
     */
    public function hasAccessToResource(TenantUser $tenantUser, string $key): bool
    {
        if ($tenantUser->isHeadCoach() && ! in_array($key, ['class_single_actions', 'class_bulk_actions'])) {
            return true;
        }

        $hasAccess = false;

        // Find the privilege by key
        $accessPrivilege = AccessPrivilege::query()->where('key', '=', $key)->first();

        if ($accessPrivilege) {

            // Check if this user has access to this resource
            $userAccessPrivilege = UserAccessPrivilege::query()
                ->where('user_id', $tenantUser->user_id)
                ->where('box_id', $tenantUser->box_id)
                ->where('access_privilege_id', $accessPrivilege->getKey())
                ->first();

            // The reason for this is not all uses in the system have access privileges.
            // We didn't want to revoke access to certain feature. e.g. super admin is one of those users
            if (! $userAccessPrivilege) {
                $hasAccess = true;
            } else {
                if (! $userAccessPrivilege->revoked_on && ! $userAccessPrivilege->revoked) {
                    $hasAccess = true;
                }
            }
        }

        return $hasAccess;
    }
}
