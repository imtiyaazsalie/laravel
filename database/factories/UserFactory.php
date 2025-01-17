<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        // Cater for no gender
        $gender = rand(0, 2);

        return [
            'name' => $gender === 1 ? fake()->firstNameMale : ($gender === 2 ? fake()->firstNameFemale : fake()->firstName),
            'surname' => fake()->lastName,
            'email' => fake()->unique()->safeEmail,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'dob' => $this->faker->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'mobile' => $this->faker->phoneNumber,
            'gender_id' => $gender !== 0 ? $gender : null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
