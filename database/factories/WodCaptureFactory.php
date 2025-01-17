<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WodCapture>
 */
class WodCaptureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wod_capture_class' => $this->faker->time('H:i'),
            'wod_capture_comments' => $this->faker->boolean(30) ? $this->faker->sentence() : null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function verifiedBy(User $user, ?Carbon $date = null)
    {
        return $this->state(fn (array $attributes) => [
            'verifier_id' => $user->getAuthIdentifier(),
            'is_verified' => true,
            'dt_verified' => $date ?: now(),
        ]);
    }
}
