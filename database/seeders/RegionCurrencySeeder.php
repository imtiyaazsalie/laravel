<?php

namespace Database\Seeders;

use App\Models\RegionCurrency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegionCurrencySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [1, 165],
            [2, 106],
            [2, 165],
            [3, 1],
            [3, 150],
            [4, 165],
            [4, 166],
            [4, 167],
            [5, 47],
            [5, 50],
            [6, 59],
            [7, 32],
            [8, 165],
            [9, 139],
            [9, 150],
            [10, 150],
            [11, 33],
            [11, 150],
            [12, 150],
            [13, 47],
            [13, 150],
            [14, 47],
            [14, 150],
            [15, 47],
            [15, 150],
            [16, 150],
            [17, 47],
            [17, 150],
            [18, 150],
            [18, 165],
            [18, 168],
            [19, 150],
            [20, 65],
            [20, 150],
            [21, 44],
            [21, 150],
            [22, 47],
            [22, 150],
            [22, 165],
            [23, 150],
            [24, 150],
            [25, 150],
            [26, 47],
            [26, 150],
            [27, 150],
            [28, 47],
            [28, 50],
            [29, 29],
            [29, 47],
            [29, 150],
            [30, 8],
            [30, 150],
            [31, 146],
            [31, 150],
            [32, 50],
            [33, 105],
            [33, 165],
            [34, 47],
            [34, 150],
            [35, 34],
            [35, 150],
            [36, 129],
            [37, 100],
            [37, 150],
            [38, 150],
            [39, 24],
            [39, 165],
            [40, 100],
            [40, 150],
            [41, 47],
            [41, 150],
            [42, 50],
            [42, 150],
            [42, 165],
            [43, 1],
            [43, 125],
            [43, 150],
        ];

        RegionCurrency::insert(array_map(function ($item) {
            return [
                'region_id' => $item[0],
                'currency_id' => $item[1],
            ];
        }, $data));
    }
}
