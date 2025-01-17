<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\TenantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;

class MigrateUserTypesToRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = Role::all();

        if ($roles->isEmpty()) {
            throw new RuntimeException('Please seed roles and permissions before migrating.');
        }

        TenantUser::query()
            ->whereNotNull('user_type_id')
            ->withTrashed()
            ->chunk(500, function ($tenantUsers) use ($roles) {

                DB::table('model_has_roles')->upsert(
                    $tenantUsers->where('user_type_id', UserType::HEAD_COACH)->map(fn ($m) => [
                        'role_id' => $roles->where('name', 'Head Coach')->first()->getKey(),
                        'model_type' => 'user',
                        'model_id' => $m->user_id,
                        'team_id' => $m->box_id,
                    ])->toArray(),
                    []
                );

                DB::table('model_has_roles')->upsert(
                    $tenantUsers->where('user_type_id', UserType::GYM_COACH)->map(fn ($m) => [
                        'role_id' => $roles->where('name', 'Gym Coach')->first()->getKey(),
                        'model_type' => 'user',
                        'model_id' => $m->user_id,
                        'team_id' => $m->box_id,
                    ])->toArray(),
                    []
                );

                DB::table('model_has_roles')->upsert(
                    $tenantUsers->where('user_type_id', UserType::GYM_MEMBER)->map(fn ($m) => [
                        'role_id' => $roles->where('name', 'Gym Member')->first()->getKey(),
                        'model_type' => 'user',
                        'model_id' => $m->user_id,
                        'team_id' => $m->box_id,
                    ])->toArray(),
                    []
                );

                DB::table('model_has_roles')->upsert(
                    $tenantUsers->where('user_type_id', UserType::BOX_ADMIN)->map(fn ($m) => [
                        'role_id' => $roles->where('name', 'Box Admin')->first()->getKey(),
                        'model_type' => 'user',
                        'model_id' => $m->user_id,
                        'team_id' => $m->box_id,
                    ])->toArray(),
                    []
                );

                DB::table('model_has_roles')->upsert(
                    $tenantUsers->where('user_type_id', UserType::BOX_FACILITY_ADMIN)->map(fn ($m) => [
                        'role_id' => $roles->where('name', 'Box Facility Admin')->first()->getKey(),
                        'model_type' => 'user',
                        'model_id' => $m->user_id,
                        'team_id' => $m->box_id,
                    ])->toArray(),
                    []
                );
            });
    }
}
