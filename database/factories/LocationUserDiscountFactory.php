<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LocationUserDiscount>
 */
class LocationUserDiscountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'starting_on' => today(),
            'ending_on' => $this->faker->boolean() ? today()->addYear() : null,
            'status' => 'active',
        ];
    }
}
