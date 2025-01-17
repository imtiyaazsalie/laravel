<?php

namespace Database\Seeders;

use App\Models\LocationUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateUserFacilityAccessPrivilegesToLocationUser extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('user_facility_access_privileges')
            ->where('revoked', false)
            ->orderBy('user_facility_access_privilege_id')
            ->chunk(1000, function ($privileges) {
                foreach ($privileges as $privilege) {
                    LocationUser::create([
                        'user_id' => $privilege->user_id,
                        'box_facility_id' => $privilege->box_facility_id,
                        'effective_date' => $privilege->created_on,
                        'end_date' => now()->endOfYear(), //TODO: Set appropriate end date
                    ]);
                }
            });
    }
}
