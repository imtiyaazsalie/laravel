<?php

namespace Database\Factories;

use App\Enums\PaymentGatewayContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LocationPaymentGateway>
 */
class LocationPaymentGatewayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'credentials' => 'TEestasefa3rq234fawerfe',
            'is_active' => true,
            'context' => PaymentGatewayContext::DEBIT_ORDER,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function adHoc()
    {
        return $this->state(fn (array $attributes) => [
            'context' => PaymentGatewayContext::AD_HOC,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
