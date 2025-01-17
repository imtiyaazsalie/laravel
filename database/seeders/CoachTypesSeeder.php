<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoachTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('coach_types')->insert([
            [
                'coach_type_id' => 1,
                'coach_type_descr' => 'Head coach',
                'is_active' => true,
            ],
            [
                'coach_type_id' => 2,
                'coach_type_descr' => 'Supporting coach',
                'is_active' => true,
            ],
        ]);
    }
}
