<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::first()->getKey(),
            'street_address' => $this->faker->streetAddress,
            'city' => $this->faker->city,
            'state_province_region' => 'Somewhere',
            'postal_code' => $this->faker->postcode,
            'country_code' => $this->faker->countryCode,
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
        ];
    }
}
