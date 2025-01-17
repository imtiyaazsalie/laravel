<?php

namespace Database\Factories;

use App\Enums\ClassBookingStatus;
use App\Models\ClassRecurringBooking;
use App\Models\LeadMember;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassBooking>
 */
class ClassBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_booking_status_id' => ClassBookingStatus::BOOKED,
        ];
    }

    /**
     * Set status.
     *
     * @return static
     */
    public function status(ClassBookingStatus $status)
    {
        return $this->state(fn () => [
            'class_booking_status_id' => $status,
        ]);
    }

    /**
     * Recurring booking
     *
     * @return static
     */
    public function recurringBooking(?ClassRecurringBooking $recurringBooking = null)
    {
        return $this->state(fn () => [
            'class_recurring_booking_id' => $recurringBooking->getKey(),
        ]);
    }

    /**
     * Top up used.
     *
     * @return static
     */
    public function topUpUsed(?ClassRecurringBooking $recurringBooking = null)
    {
        return $this->state(fn () => [
            'top_up_used' => true,
        ]);
    }

    /**
     * Lead member
     *
     * @return static
     */
    public function leadMember(LeadMember $leadMember)
    {
        return $this->state(fn () => [
            'lead_member_id' => $leadMember->getKey(),
        ]);
    }

    /**
     * Non member
     *
     * @return static
     */
    public function nonMember()
    {
        return $this->state(fn () => [
            'non_member_name' => $this->faker->name(),
            'non_member_email' => $this->faker->email(),
        ]);
    }

    /**
     * Checked In
     *
     * @return static
     */
    public function checkedIn(?Carbon $date = null)
    {
        return $this->state(fn () => [
            'is_checked_in' => true,
            'checked_in_at' => $date ?: now(),
        ]);
    }

    /**
     * Checked In
     *
     * @return static
     */
    public function checkedOut(?Carbon $date = null)
    {
        return $this->state(fn (array $attributes) => [
            'is_checked_out' => true,
            'checked_out_at' => $date ?: $attributes['checked_in_at']->addMinutes(random_int(15, 300)),
        ]);
    }
}
