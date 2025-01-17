<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserPackage>
 */
class UserPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'effective_date' => today()->subDay(),
            'end_date' => $endDate = today()->subDay()->addYear(),
            'sessions_available' => $this->faker->numberBetween(10, 50),
            'sessions_expire' => $endDate,
            'deleted' => false,
        ];
    }
}
