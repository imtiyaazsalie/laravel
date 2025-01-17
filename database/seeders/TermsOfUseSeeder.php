<?php

namespace Database\Seeders;

use App\Models\TermsOfUse;
use Illuminate\Database\Seeder;

class TermsOfUseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TermsOfUse::insert([
            'id' => 1,
            'content' => '<p>Test terms of use</p>',
            'created_on' => now(),
            'released_on' => now(),
        ]);
    }
}
