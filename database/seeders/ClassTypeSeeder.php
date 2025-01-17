<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('class_types')->insert([
            [
                'class_type_id' => 1,
                'class_type_descr' => 'Once-off',
            ],
            [
                'class_type_id' => 2,
                'class_type_descr' => 'Recurring',
            ],
        ]);
    }
}
