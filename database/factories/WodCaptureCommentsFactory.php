<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WodCapture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WodCaptureComments>
 */
class WodCaptureCommentsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => $this->faker->sentence(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function forCapture(WodCapture $wodCapture)
    {
        return $this->state(fn (array $attributes) => [
            'wod_capture_id' => $wodCapture->getKey(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function createdBy(User $user)
    {
        return $this->state(fn (array $attributes) => [
            'created_by_id' => $user->getAuthIdentifier(),
        ]);
    }
}
