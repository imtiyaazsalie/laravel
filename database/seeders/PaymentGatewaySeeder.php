<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentGateway::insert([
            [
                'payment_gateway_id' => 1,
                'payment_gateway_name' => 'No gateway',
                'is_active' => true,
            ],
            [
                'payment_gateway_id' => 2,
                'payment_gateway_name' => 'SagePay',
                'is_active' => true,
            ],
            [
                'payment_gateway_id' => 3,
                'payment_gateway_name' => 'Three Peaks',
                'is_active' => true,
            ],
            [
                'payment_gateway_id' => 4,
                'payment_gateway_name' => 'Netcash',
                'is_active' => true,
            ],
            [
                'payment_gateway_id' => 5,
                'payment_gateway_name' => 'GoCardless',
                'is_active' => true,
            ],
            [
                'payment_gateway_id' => 6,
                'payment_gateway_name' => 'Stripe',
                'is_active' => true,
            ],
            [
                'payment_gateway_id' => 7,
                'payment_gateway_name' => 'Paystack',
                'is_active' => true,
            ],
        ]);
    }
}
