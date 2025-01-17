<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BodyWeight>
 */
class BodyWeightFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weight' => number_format($this->faker->numberBetween(5000, 12000) / 100, 2, '.', ''),
            'recorded_on' => now()->subMinutes(random_int(1, 5)),
        ];
    }
}
