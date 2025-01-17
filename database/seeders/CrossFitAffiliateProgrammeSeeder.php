<?php

namespace Database\Seeders;

use App\Enums\Affiliate;
use App\Models\Programme;
use Illuminate\Database\Seeder;

class CrossFitAffiliateProgrammeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Programme::globalAffiliateProgrammes(Affiliate::CrossFit)->exists()) {
            return;
        }

        $data = collect([
            [
                'name' => 'CrossFit Affiliate',
            ],
            [
                'name' => 'CrossFit Compete',
            ],
        ]);

        Programme::insert(
            $data->map(fn ($programme) => array_merge($programme, [
                'affiliate_id' => Affiliate::CrossFit->value,
                'is_active' => true,
                'created_by_id' => 1,
                'created_on' => now(),
                'updated_on' => now(),
            ]))->toArray()
        );

    }
}
