<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Country::insert([
            [
                'country_name' => 'South Africa',
                'code' => 'ZA',
                'country_timezone' => 2,
                'region_id' => 1,
            ],
            [
                'country_name' => 'Namibia',
                'code' => 'NA',
                'country_timezone' => 2,
                'region_id' => 2,
            ],
            [
                'country_name' => 'United Arab Emirates',
                'code' => 'AE',
                'country_timezone' => 4,
                'region_id' => 4,
            ],
        ]);
    }
}
