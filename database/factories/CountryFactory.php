<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_name' => $this->faker->country(),
            'code' => $code = $this->faker->countryCode(),
            'country_timezone' => $this->faker->timezone($code),
            'region_id' => Region::inRandomOrder()->first()->getKey(),
        ];
    }
}
