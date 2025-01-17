<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Bank;
use App\Models\DebitDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserBankingDetail>
 */
class UserBankingDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_id' => Bank::inRandomOrder()->first()->getKey(),
            'account_type_id' => AccountType::SAVINGS,
            'debit_day_id' => DebitDay::inRandomOrder()->first()->getKey(),
            'account_no' => $this->faker->numberBetween(123414543, 999999999),
            'account_name' => $this->faker->firstName().' '.$this->faker->lastName(),
            'waiver' => null,
            'is_active' => true,
            'bank_other' => null,
            'branch_code' => $this->faker->numberBetween(21230, 94542),
        ];
    }
}
