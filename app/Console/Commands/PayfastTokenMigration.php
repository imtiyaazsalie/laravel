<?php

namespace App\Console\Commands;

use App\Enums\FinancePaymentTokenType;
use App\Enums\PaymentGateway;
use App\Models\FinancePaymentGateway;
use App\Models\FinancePaymentToken;
use App\Models\Location;
use Illuminate\Console\Command;

class PayfastTokenMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:payfast-token-migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate the payfast tokens to the finance payment tokens table';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        FinancePaymentGateway::where('gateway', '=', 'payfast')
            ->each(function (FinancePaymentGateway $paymentGateway) {
                $paymentGateway->location_id;

                Location::find($paymentGateway->location_id)->update([
                    'billing_payment_gateway_id' => PaymentGateway::PAYFAST,
                ]);

                FinancePaymentToken::firstOrCreate([
                    'location_id' => $paymentGateway->location_id,
                    'payment_gateway_id' => PaymentGateway::PAYFAST,
                    'token' => $paymentGateway->payfast_subscription_id,
                    'type' => FinancePaymentTokenType::LOCATION,
                ], [
                    'payment_method' => 'card',
                    'created_at' => $paymentGateway->created_on,
                    'updated_at' => $paymentGateway->updated_on,
                ]);
            });

        // Set all NON SA location to payfast
        Location::whereHas('tenant.region', function ($query) {
            $query->where('region_id', '!=', 1);
        })->update([
            'billing_payment_gateway_id' => PaymentGateway::PAYFAST,
        ]);
    }
}
