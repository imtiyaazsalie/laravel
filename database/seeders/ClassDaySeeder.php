<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassDaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('class_days')->insert([
            [
                'class_day_id' => 1,
                'class_day_descr' => 'Monday',
            ],
            [
                'class_day_id' => 2,
                'class_day_descr' => 'Tuesday',
            ],
            [
                'class_day_id' => 3,
                'class_day_descr' => 'Wednesday',
            ],
            [
                'class_day_id' => 4,
                'class_day_descr' => 'Thursday',
            ],
            [
                'class_day_id' => 5,
                'class_day_descr' => 'Friday',
            ],
            [
                'class_day_id' => 6,
                'class_day_descr' => 'Saturday',
            ],
            [
                'class_day_id' => 7,
                'class_day_descr' => 'Sunday',
            ],
        ]);
    }
}
