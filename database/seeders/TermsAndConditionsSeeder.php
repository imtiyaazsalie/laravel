<?php

namespace Database\Seeders;

use App\Models\TermsConditions;
use Illuminate\Database\Seeder;

class TermsAndConditionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TermsConditions::class::insert([
            'id' => 1,
            'content' => '<p>Test terms and conditions</p>',
            'created_on' => now(),
            'released_on' => now(),
        ]);
    }
}
