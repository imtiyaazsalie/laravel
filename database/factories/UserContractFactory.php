<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserContract>
 */
class UserContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'starting_on' => now()->subDay(),
            'ending_on' => now()->addYear(),

            'file_path' => null,
            'file_mime' => null,
            'file_name' => null,
            'contract_terms_and_conditions' => null,

            'accepted' => $accepted = $this->faker->boolean(),
            'accepted_on' => $accepted ? $this->faker->dateTime() : null,

            'sent' => $sent = $this->faker->boolean(),
            'sent_on' => $sent ? $this->faker->dateTime() : null,

            'ip_address' => $this->faker->ipv4(),
        ];
    }
}
