<?php

namespace Database\Seeders;

use App\Models\LocationPaymentGateway;
use App\Models\LocationPaymentGatewaySettings;
use Illuminate\Database\Seeder;
use RuntimeException;

class DisarmPaymentGatewaySettings extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        if (! app()->environment('local', 'staging', 'development')) {
            throw new RuntimeException('This should not run in production.');
        }

        LocationPaymentGateway::query()
            ->whereNotNull('credentials')
            ->update([
                'credentials' => 'redacted&redacted&redacted',
            ]);

        LocationPaymentGatewaySettings::query()
            ->update([
                'username' => 'redacted',
                'password' => 'redacted',
                'pin' => 'redacted',
                'merchant_account_number' => 'redacted',
                'debit_order_service_key' => 'redacted',
                'pay_now_service_key' => 'redacted',
                'account_service_key' => 'redacted',
                'secret_key' => 'redacted',
                'public_key' => 'redacted',
                'token' => 'redacted',
                'public_token' => 'redacted',
            ]);

    }
}
