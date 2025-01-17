<?php

namespace Database\Factories;

use App\Enums\ClassType;
use App\Models\Classes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classes>
 */
class ClassesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_type_id' => ClassType::ONCE_OFF,
            'class_name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'end_time' => $endTime = $this->faker->time('H:i:s'),
            'start_time' => $this->faker->time('H:i:s', now()->setTimeFromTimeString($endTime)->subMinutes(15)),
            'class_limit' => $this->faker->numberBetween(5, 50),
            'cancellation_threshhold' => $this->faker->numberBetween(60, 5000),
            'is_free' => false,
            'dt_added' => now(),
            'dt_modified' => now(),
            'is_active' => true,
            'booking_threshold' => $this->faker->numberBetween(1, 2),
            'is_display_coach_name' => true,
            'dt_deactivated' => null,
            'updated_by_id' => null,
            'recurring_end_date' => null,
            'is_session' => false,
            'is_visible_in_app' => true,
            'meeting_url' => $this->faker->url,
            'is_virtual' => false,
        ];
    }

    /**
     * Set capturer.
     */
    public function capturedBy(User $user): ClassesFactory|Factory
    {
        return $this->state(fn () => [
            'capturer_id' => $user->getAuthIdentifier(),
        ]);
    }

    /**
     * Inactive class.
     */
    public function inactive(): ClassesFactory|Factory
    {
        return $this->state(fn () => [
            'is_active' => false,
            'dt_deactivated' => now(),
        ]);
    }

    /**
     * Recurring class.
     */
    public function recurring(Carbon $endDate): ClassesFactory|Factory
    {
        return $this->state(fn () => [
            'class_type_id' => ClassType::RECURRING,
            'recurring_end_date' => $endDate,
        ]);
    }

    /**
     * Free class.
     */
    public function free(): static
    {
        return $this->state(fn () => [
            'is_free' => true,
        ]);
    }

    /**
     * NotVisibleInApp class.
     */
    public function notVisibleInApp(): static
    {
        return $this->state(fn () => [
            'is_visible_in_app' => false,
        ]);
    }

    /**
     * Session class.
     */
    public function session(): ClassesFactory|Factory
    {
        return $this->state(fn () => [
            'is_session' => true,
        ]);
    }

    /**
     * Virtual class.
     */
    public function virtual(): ClassesFactory|Factory
    {
        return $this->state(fn () => [
            'is_virtual' => true,
            'meeting_url' => $this->faker->url(),
        ]);
    }
}
