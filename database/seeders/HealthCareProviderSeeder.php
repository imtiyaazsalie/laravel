<?php

namespace Database\Seeders;

use App\Models\HealthCareProvider;
use Illuminate\Database\Seeder;

class HealthCareProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        HealthCareProvider::insert([
            [
                'id' => 1,
                'name' => 'Discovery Vitality',
                'is_active' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Momentum Multiply',
                'is_active' => 0,
            ],
        ]);
    }
}
