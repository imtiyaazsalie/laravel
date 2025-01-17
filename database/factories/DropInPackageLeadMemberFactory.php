<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DropInPackageLeadMember>
 */
class DropInPackageLeadMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sessions_purchased' => $purchased = $this->faker->numberBetween(10, 20),
            'sessions_remaining' => $this->faker->numberBetween(0, $purchased),
            'token' => $this->faker->md5(),
            'is_confirmation_sent' => false,
        ];
    }
}
