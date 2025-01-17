<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageLimitTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('package_limit_types')->insert([
            [
                'package_limit_type_id' => 1,
                'package_limit_type_descr' => 'Monthly',
                'is_active' => true,
            ],
            [
                'package_limit_type_id' => 2,
                'package_limit_type_descr' => 'Weekly',
                'is_active' => true,
            ],
            [
                'package_limit_type_id' => 3,
                'package_limit_type_descr' => 'Limited',
                'is_active' => true,
            ],
        ]);
    }
}
