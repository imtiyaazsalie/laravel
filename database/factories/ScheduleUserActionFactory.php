<?php

namespace Database\Factories;

use App\Enums\ScheduleUserActionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduleUserAction>
 */
class ScheduleUserActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ScheduleUserActionStatus::PENDING,
            'date' => today(),

            'on_hold_note' => $this->faker->sentence(),
            'on_hold_pro_rata_fee' => $this->faker->randomFloat(2, 0, 250),
            'on_hold_release_date' => today()->addWeek(),

            'exclude_from_future_batches' => false,
            'last_debit_date' => null,

            'failed_attempts' => 0,

            'extend_package_end_date' => 0,
            'tenant_user_id' => 294,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function onHold()
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'onHold',
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function deactivate()
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'deactivate',
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function cancelled()
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleUserActionStatus::CANCELLED,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function complete()
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleUserActionStatus::COMPLETE,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function excludeFromFutureBatches(?Carbon $from = null)
    {
        return $this->state(fn (array $attributes) => [
            'exclude_from_future_batches' => true,
            'last_debit_date' => $from,
        ]);
    }
}
