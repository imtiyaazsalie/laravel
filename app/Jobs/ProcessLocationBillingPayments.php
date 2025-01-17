<?php

namespace App\Jobs;

use App\Enums\FinancePaymentTokenType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Models\FinancePaymentToken;
use App\Models\Location;
use App\Services\LocationService;
use App\Services\NonceService;
use App\Services\PaymentGateways\PayFastService;
use App\Services\PaymentGateways\PaystackService;
use App\Services\PaymentGateways\StripeConnectService;
use App\Services\PaymentTokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLocationBillingPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Location $location, private readonly float $amount)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(LocationService $locationService): void
    {
        $locationInvoice = $locationService->createLocationInvoice($this->location, $this->amount, InvoiceStatus::PENDING);

        if ($this->location->billing_payment_gateway_id === PaymentGateway::PAYFAST) {
            $dateStr = now()->format('M Y');
            $result = (new PayFastService())->doPayment($this->location, $locationInvoice, "{$this->location->name}: $dateStr ({$locationInvoice->getKey()})");

            if ($result['status'] !== 'success') {
                $locationInvoice->update(['status' => InvoiceStatus::UNPAID], ['facility_invoice_id' => $locationInvoice['id']]);
                Log::error($result);
            }
        } elseif ($this->location->billing_payment_gateway_id === PaymentGateway::PAYSTACK) {
            $paymentToken = (new PaymentTokenService())->getFinancePaymentToken(
                paymentMethods: ['card'],
                financePaymentTokenType: FinancePaymentTokenType::LOCATION,
                location: $this->location,
                paymentGateway: PaymentGateway::PAYSTACK
            );

            if (! $paymentToken instanceof FinancePaymentToken) {
                Log::info('Location('.$this->location->name.') does not a payment token (invoice '.$locationInvoice->getKey().').');

                return;
            }

            $paystackService = new PaystackService();
            $customer = $paystackService->fetchCustomer($paymentToken->customer_id);

            if (! $customer) {
                Log::info('Paystack customer('.$paymentToken->customer_id.') could not be found for '.$this->location->name);

                return;
            }

            $responseData = $paystackService->chargeAuthorization([
                'amount' => $locationInvoice->amount_in_cents,
                'email' => $customer['email'],
                'currency' => 'ZAR',
                'reference' => (new NonceService())->generateNonce($locationInvoice->getKey()),
                'authorization_code' => $paymentToken->token,
                'metadata' => [
                    'type' => FinancePaymentTokenType::LOCATION->value,
                    'custom_fields' => [
                        [
                            'display_name' => 'Location Name',
                            'variable_name' => 'location_name',
                            'value' => $this->location->name,
                        ],
                        [
                            'display_name' => 'Invoice Description',
                            'variable_name' => 'invoice_description',
                            'value' => $locationInvoice->description,
                        ],
                        [
                            'display_name' => 'Invoice Code',
                            'variable_name' => 'invoice_code',
                            'value' => $locationInvoice->code,
                        ],
                        [
                            'display_name' => 'Invoice ID',
                            'variable_name' => 'invoice_id',
                            'value' => $locationInvoice->getKey(),
                        ],
                    ],
                ],
            ]);

            if ($responseData) {
                $locationInvoice->update([
                    'status' => InvoiceStatus::SUBMITTED,
                    'note' => json_encode($responseData['data']),
                ]);
            }
        } elseif ($this->location->billing_payment_gateway_id === PaymentGateway::STRIPE_CONNECT) {
            $paymentToken = (new PaymentTokenService())->getFinancePaymentToken(
                paymentMethods: ['card'],
                financePaymentTokenType: FinancePaymentTokenType::LOCATION,
                location: $this->location,
                paymentGateway: PaymentGateway::STRIPE_CONNECT
            );

            if (! $paymentToken instanceof FinancePaymentToken) {
                Log::info('Location('.$this->location->name.') does not a payment token (invoice '.$locationInvoice->getKey().').');

                return;
            }

            (new StripeConnectService())->createPaymentIntent([
                'customer' => $paymentToken->customer_id,
                'payment_method_types' => [$paymentToken->payment_method],
                'payment_method' => $paymentToken->token,
                'amount' => $locationInvoice->amount_in_cents,
                'currency' => $this->location->tenant->tenantCurrency->code,
                'confirm' => true,
                'off_session' => true,
                'description' => $locationInvoice->description.' '.$locationInvoice->code,
                'metadata' => [
                    'location_invoice_id' => $locationInvoice->getKey(),
                ],
            ], [
                'idempotency_key' => $locationInvoice->getKey(),
            ]);
        }
    }
}
