<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [1, 'South Africa', true],
            [2, 'Namibia', true],
            [3, 'Dubai - UAE', true],
            [4, 'Zambia', true],
            [5, 'United Kingdom', true],
            [6, 'Hong Kong', false],
            [7, 'China', false],
            [8, 'DRC', true],
            [9, 'Thailand', false],
            [10, 'Thailand', true],
            [11, 'South America', false],
            [12, 'USA', false],
            [13, 'Germany', true],
            [14, 'Italy', false],
            [15, 'Austria', true],
            [16, 'Indiana, US', false],
            [17, 'Belgium', true],
            [18, 'Zimbabwe', true],
            [19, 'Vietnam', true],
            [20, 'Israel', false],
            [21, 'Egypt', false],
            [22, 'Cyprus', false],
            [23, 'Japan', false],
            [24, 'Netherlands', true],
            [25, 'USA, WA', false],
            [26, 'Spain', false],
            [27, 'Singapore', true],
            [28, 'Trial Region', false],
            [29, 'Switzerland', true],
            [30, 'Australia', false],
            [31, 'Taiwan', false],
            [32, 'Scotland', false],
            [33, 'Mozambique', true],
            [34, 'Portugal', false],
            [35, 'Costa Rica', true],
            [36, 'Sweden', false],
            [37, 'Mauritius', false],
            [38, 'Tunisia', true],
            [39, 'Botswana', true],
            [40, 'Mauritius', true],
            [41, 'Greece', true],
            [42, 'Octiv Demo', true],
            [43, 'Saudi Arabia', true],
        ];

        Region::insert(array_map(function ($item) {
            return [
                'region_id' => $item[0],
                'region_desc' => $item[1],
                'is_active' => $item[2],
            ];
        }, $data));
    }
}
