<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SymphonyToLaravelSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            //RolesAndPermissionsSeeder::class,
            //MigrateUserTypesToRolesSeeder::class,
            //MigrateAccessPrivilegesToPermissions::class,
            //MigrateUserFacilityAccessPrivilegesToLocationUser::class,
            RemoveDuplicateUserTenants::class,
            MigrateCrmMailersMorphs::class,
            MigrateCrmMailersSchedule::class,
            MigrateUsersOnHold::class,
            LinkedUsersSeeder::class,
        ]);
    }
}
