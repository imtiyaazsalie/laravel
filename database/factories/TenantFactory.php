<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Currency;
use App\Models\Region;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $region = Region::first();

        return [
            'box_status_id' => TenantStatus::ACTIVE,
            'region_id' => $region->getKey(),
            'box_desc' => $this->faker->company,
            'description' => $this->faker->sentence,
            'website_url' => 'https://octivfitness.com',
            'instagram_url' => 'https://instagram.com/octivfitness',
            'facebook_url' => 'https://facebook.com/OctivFitness',
            'booking_threshold' => 7,
            'timezone_id' => $region->timezones->first()->getKey(),
            'box_billing_currency_id' => Currency::first()->getKey(),
            'member_billing_currency_id' => Currency::first()->getKey(),
            'deactivate_contracts_ended' => true,
            'max_bookings_per_athlete_per_day' => 3,
            'cash_member_invoice_generation_day' => '25',
            'cash_member_invoice_strategy' => 'generate_only',
            'cash_member_invoice_due_day' => 1,
            'signup_use_contract_and_waivers' => true,
            'pro_rate_strategy' => 'manual',
            // 'signup_payment_options' => [1, 2, 5],
            // 'signup_debit_day_options' => [1, 2, 3, 4, 5, 6, 7],
            // Todo json/array db stuff
        ];
    }
}
