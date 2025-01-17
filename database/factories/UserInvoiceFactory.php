<?php

namespace Database\Factories;

use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserInvoice>
 */
class UserInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomFloat(2, 50, 600),
            'type' => InvoiceType::INVOICE,
            'status' => InvoiceStatus::UNPAID,
            'description' => $this->faker->sentence(),
            'code' => $this->faker->md5(),
            'discriminator' => InvoiceDiscriminator::INVOICE,
            'deleted' => false,
            'currency' => Currency::first()->code,
        ];
    }
}
