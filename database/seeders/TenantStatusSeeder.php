<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantStatusSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('box_status')->insert([
            [
                'box_status_desc' => 'Active',
            ],
            [
                'box_status_desc' => 'Inactive',
            ],
            [
                'box_status_desc' => 'Suspended',
            ],
            [
                'box_status_desc' => 'Deleted',
            ],
        ]);
    }
}
