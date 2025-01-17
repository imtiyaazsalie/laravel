<?php

namespace Database\Seeders;

use App\Models\TenantUser;
use App\Traits\MapsPrivilegesToPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class MigrateAccessPrivilegesToPermissions extends Seeder
{
    use MapsPrivilegesToPermissions;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TenantUser::query()
            ->select('user_to_box.*')
            ->whereNotNull('user_type_id')
            ->join('user_access_privileges', function ($join) {
                $join->on('user_access_privileges.user_id', '=', 'user_to_box.user_id');
                $join->on('user_access_privileges.box_id', '=', 'user_to_box.box_id');
            })
            ->where('user_access_privileges.revoked', true)
            ->orderBy('user_to_box.user_to_box_id')
            ->withTrashed()
            ->chunk(500, function ($tenantUsers) {

                foreach ($tenantUsers as $tenantUser) {
                    setPermissionsTeamId($tenantUser->box_id);

                    $userAccessPrivileges = DB::table('user_access_privileges')
                        ->select(['user_access_privileges.*', 'access_privileges.key'])
                        ->join('access_privileges', 'access_privileges.access_privilege_id', '=', 'user_access_privileges.access_privilege_id')
                        ->where('user_access_privileges.revoked', true)
                        ->where('user_access_privileges.user_id', $tenantUser->user_id)
                        ->where('user_access_privileges.box_id', $tenantUser->box_id)
                        ->get();

                    /**
                     * Figure out permissions from privilege keys.
                     */
                    $privilegeKeys = $userAccessPrivileges->pluck('key')->unique('key')->toArray();

                    if (empty($privilegeKeys)) {
                        continue;
                    }

                    $permissionsToRevoke = [];

                    foreach ($privilegeKeys as $key) {
                        $permissionsToRevoke = array_merge($permissionsToRevoke, $this->mapPrivilegeToPermissions($key));
                    }

                    if (empty($permissionsToRevoke)) {
                        continue;
                    }

                    $rolePermissions = $tenantUser->user->getPermissionsViaRoles();

                    $tenantUserPermissions = array_diff($rolePermissions->pluck('name')->toArray(), $permissionsToRevoke);

                    /**
                     * Revoke permissions by.
                     *
                     * 1. Fetch box custom roles with permissions.
                     * 2. If box has custom role that matches all $permissions assign it to the user.
                     * 3. If box does not have a custom role that matches create one with $permissions
                     */
                    $roles = Role::query()
                        ->with('permissions')
                        ->where('team_id', $tenantUser->box_id)
                        ->orderBy('team_id')
                        ->get();

                    $matchingRole = $roles->filter(fn ($role) => empty(
                        collect($tenantUserPermissions)->sort()->diff(
                            $role->permissions->pluck('name')->sort()
                        )->toArray()
                    ))->first();

                    if ($matchingRole) {
                        $tenantUser->user->syncRoles($matchingRole);

                        continue;
                    }

                    $newRole = DB::transaction(function () use ($roles, $tenantUser, $tenantUserPermissions) {

                        $newRole = Role::create([
                            'name' => 'Custom Role '.$roles->count() + 1,
                            'description' => 'This custom role name and description should be changed.',
                            'team_id' => $tenantUser->box_id,
                        ]);

                        $newRole->syncPermissions($tenantUserPermissions);

                        return $newRole;
                    });

                    $tenantUser->user->syncRoles($newRole);
                }
            });
    }
}
