<?php

namespace Database\Seeders;

use App\Models\DebitDay;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DebitDaySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DebitDay::insert([
            [
                'debit_day_id' => 1,
                'debit_day_descr' => '1st of the month',
                'is_active' => true,
                'interval' => 'P5D',
                'order' => 1,
                'import_code' => 1,
            ],
            [
                'debit_day_id' => 2,
                'debit_day_descr' => '15th of the month',
                'is_active' => true,
                'interval' => 'P5D',
                'order' => 3,
                'import_code' => 15,
            ],
            [
                'debit_day_id' => 3,
                'debit_day_descr' => '25th of the month',
                'is_active' => true,
                'interval' => 'P5D',
                'order' => 4,
                'import_code' => 25,
            ],
            [
                'debit_day_id' => 4,
                'debit_day_descr' => 'Last day of the month',
                'is_active' => true,
                'interval' => 'P5D',
                'order' => 7,
                'import_code' => 31,
            ],
            [
                'debit_day_id' => 5,
                'debit_day_descr' => '27th of the month',
                'is_active' => true,
                'interval' => 'P5D',
                'order' => 5,
                'import_code' => 27,
            ],
            [
                'debit_day_id' => 6,
                'debit_day_descr' => '5th of the month',
                'is_active' => true,
                'interval' => 'P5D',
                'order' => 2,
                'import_code' => 5,
            ],
            [
                'debit_day_id' => 7,
                'debit_day_descr' => '28th of the month',
                'is_active' => true,
                'interval' => 'P0D',
                'order' => 6,
                'import_code' => 28,
            ],
        ]);
    }
}
