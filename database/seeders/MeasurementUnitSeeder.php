<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MeasurementUnitSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MeasurementUnit::insert([
            [
                'measuring_unit_id' => 1,
                'measuring_unit_short' => 'kg',
                'measuring_unit_desc' => 'For Weight - kg',
                'is_active' => 1,
                'measuring_unit_format' => '80 for 80kg',
            ],
            [
                'measuring_unit_id' => 2,
                'measuring_unit_short' => 'mm.ss',
                'measuring_unit_desc' => 'For Time - max',
                'is_active' => 1,
                'measuring_unit_format' => '10.30 for 10 min 30 secs',
            ],
            [
                'measuring_unit_id' => 3,
                'measuring_unit_short' => 'ml',
                'measuring_unit_desc' => 'For Millilitres',
                'is_active' => 0,
                'measuring_unit_format' => '1000 for 1000ml',
            ],
            [
                'measuring_unit_id' => 4,
                'measuring_unit_short' => 'reps',
                'measuring_unit_desc' => 'For Repetitions',
                'is_active' => 1,
                'measuring_unit_format' => '12 for 12 reps',
            ],
            [
                'measuring_unit_id' => 5,
                'measuring_unit_short' => 'rounds',
                'measuring_unit_desc' => 'For Rounds',
                'is_active' => 1,
                'measuring_unit_format' => '4 for 4 rounds',
            ],
            [
                'measuring_unit_id' => 6,
                'measuring_unit_short' => 'm',
                'measuring_unit_desc' => 'For Distance - metres',
                'is_active' => 1,
                'measuring_unit_format' => '20.5 for 20.5 metres',
            ],
            [
                'measuring_unit_id' => 7,
                'measuring_unit_short' => 'km',
                'measuring_unit_desc' => 'For Distance - kilometres',
                'is_active' => 1,
                'measuring_unit_format' => '10.5 for 10.5 km',
            ],
            [
                'measuring_unit_id' => 8,
                'measuring_unit_short' => 'laps',
                'measuring_unit_desc' => 'For Distance - laps',
                'is_active' => 1,
                'measuring_unit_format' => '2 for 2 laps',
            ],
            [
                'measuring_unit_id' => 9,
                'measuring_unit_short' => 'rnds.reps',
                'measuring_unit_desc' => 'For Rounds & Reps',
                'is_active' => 1,
                'measuring_unit_format' => '3.15 for 3 rounds 15 reps',
            ],
            [
                'measuring_unit_id' => 10,
                'measuring_unit_short' => 'cal',
                'measuring_unit_desc' => 'For Calories',
                'is_active' => 1,
                'measuring_unit_format' => '1000 for 1000 calories',
            ],
            [
                'measuring_unit_id' => 11,
                'measuring_unit_short' => 'm:cm',
                'measuring_unit_desc' => 'For Distance - metres & centimetres',
                'is_active' => 1,
                'measuring_unit_format' => '5:20 for 5 metres 20 cm',
            ],
            [
                'measuring_unit_id' => 12,
                'measuring_unit_short' => 'Exercise C',
                'measuring_unit_desc' => 'None',
                'is_active' => 0,
                'measuring_unit_format' => '',
            ],
            [
                'measuring_unit_id' => 13,
                'measuring_unit_short' => 'No measure',
                'measuring_unit_desc' => 'No measure',
                'is_active' => 1,
                'measuring_unit_format' => '',
            ],
            [
                'measuring_unit_id' => 14,
                'measuring_unit_short' => 'mm.ss',
                'measuring_unit_desc' => 'For Time - min',
                'is_active' => 1,
                'measuring_unit_format' => '10.30 for 10 min 30 secs',
            ],
            [
                'measuring_unit_id' => 15,
                'measuring_unit_short' => 'Lbs',
                'measuring_unit_desc' => 'For Weight - lbs',
                'is_active' => 1,
                'measuring_unit_format' => '120 for 120 lbs',
            ],
        ]);
    }
}
