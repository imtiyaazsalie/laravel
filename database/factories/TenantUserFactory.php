<?php

namespace Database\Factories;

use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\TenantUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantUser>
 */
class TenantUserFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $dateTimeThisDecade = $this->faker->dateTimeThisDecade()->format('Y-m-d H:i:s');

        return [
            'effective_date' => now()->subDay(),
            'end_date' => now()->subDay()->addYear(),
            'user_status_id' => UserStatus::ACTIVE->value,
            'user_debit_status_id' => UserDebitStatus::CASH->value,
            'activated_on' => $dateTimeThisDecade,
            'created_on' => $dateTimeThisDecade,
        ];
    }

    public function headCoach(): TenantUserFactory|Factory
    {
        return $this->state(fn (array $attributes) => [
            'user_type_id' => UserType::HEAD_COACH->value,
        ]);
    }

    public function tenantAdmin(): TenantUserFactory|Factory
    {
        return $this->state(fn (array $attributes) => [
            'user_type_id' => UserType::BOX_ADMIN->value,
        ]);
    }

    public function gymCoach(): TenantUserFactory|Factory
    {
        return $this->state(fn (array $attributes) => [
            'user_type_id' => UserType::GYM_COACH->value,
        ]);
    }

    public function locationAdmin(): TenantUserFactory|Factory
    {
        return $this->state(fn (array $attributes) => [
            'user_type_id' => UserType::BOX_FACILITY_ADMIN->value,
        ]);
    }

    public function member(): TenantUserFactory|Factory
    {
        return $this->state(fn (array $attributes) => [
            'user_type_id' => UserType::GYM_MEMBER->value,
        ]);
    }
}
