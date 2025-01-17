<?php

namespace Database\Factories;

use App\Enums\LeadMemberStatus;
use App\Enums\LeadMemberType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeadMember>
 */
class LeadMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'gender' => $this->faker->boolean() ? 'm' : 'f',
            'date_of_birth' => $this->faker->date('Y-m-d', now()->subYears(18)),
            'mobile_number' => $this->faker->e164PhoneNumber(),
            'email_address' => $this->faker->email(),
            'status' => LeadMemberStatus::DROP_IN,
            'notes' => '',
            'converted_on' => null,
            'type' => LeadMemberType::DROP_IN,
            'last_contacted_date' => null,
            'next_follow_up_date' => null,
            'source' => null,
            'deleted' => false,
        ];
    }
}
