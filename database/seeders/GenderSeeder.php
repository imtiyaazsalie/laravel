<?php

namespace Database\Seeders;

use App\Enums\Gender;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('gender')->insert([
            [
                'gender_id' => Gender::MALE,
                'gender_desc' => Gender::MALE->toString(),

            ],
            [
                'gender_id' => Gender::FEMALE,
                'gender_desc' => Gender::FEMALE->toString(),
            ],
        ]);
    }
}
