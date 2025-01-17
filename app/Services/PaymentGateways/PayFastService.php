<?php

namespace App\Services\PaymentGateways;

use App\Enums\FinancePaymentTokenType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Models\FinancePaymentToken;
use App\Models\Location;
use App\Models\LocationInvoice;
use App\Models\LocationPayment;
use App\Services\PaymentTokenService;
use Exception;
use Illuminate\Support\Facades\Log;
use PayFast\PayFastApi;
use PayFast\PayFastPayment;

class PayFastService
{
    public function initPayment(): PayFastPayment
    {
        try {
            return new PayFastPayment([
                'merchantId' => config('payfast.merchantId'),
                'merchantKey' => config('payfast.merchantKey'),
                'passPhrase' => config('payfast.passPhrase'),
                'testMode' => (bool) config('payfast.isTestMode'),
            ]);
        } catch (Exception $e) {
            abort(400, 'There was an exception: '.$e->getMessage());
        }
    }

    public function initAPI(): PayFastApi
    {
        try {
            return new PayFastApi([
                'merchantId' => config('payfast.merchantId'),
                'passPhrase' => config('payfast.passPhrase'),
                'testMode' => (bool) config('payfast.isTestMode'),
            ]);
        } catch (Exception $e) {
            abort(400, 'There was an exception: '.$e->getMessage());
        }
    }

    public function getOnboardingForm(Location $location)
    {
        return $this->initPayment()->custom->createFormFields([
            'return_url' => config('octiv.web_app_url').'/settings/payment-gateways/callback',
            'cancel_url' => config('octiv.web_app_url').'/settings/payment-gateways',
            'notify_url' => config('app.url').'/api/payfast/subscriptions/ping',
            'name_first' => auth()->user()->name,
            'name_last' => auth()->user()->surname,
            'email_address' => auth()->user()->email,
            'm_payment_id' => $location->getKey(),
            'amount' => '5',
            'item_name' => 'Octiv Monthly Subscription - R5.00 ZAR Initiation Fee',
            'item_description' => 'AdHoc OnBoarding',
            'custom_int1' => $location->tenant_id,
            'custom_int2' => auth()->user()->getKey(),
            'custom_int3' => $location->getKey(),
            'custom_str1' => date('Y-m-d\TH:i:s'),
            'payment_method' => 'cc',
            'subscription_type' => 2,
            'billing_date' => date('Y-m-d'),
        ], [
            'value' => 'Onboard Now',
            'class' => 'payfast-button',
        ]);
    }

    public function doPayment(Location $location, LocationInvoice $invoice, $description)
    {
        $locationPaymentToken = (new PaymentTokenService())->getFinancePaymentToken(
            paymentMethods: ['card'],
            financePaymentTokenType: FinancePaymentTokenType::LOCATION,
            location: $location,
            paymentGateway: PaymentGateway::PAYFAST
        );

        if (! $locationPaymentToken || ! $locationPaymentToken->token) {
            abort(400, "Facility for Invoice ID {$invoice->getKey()} does not have a valid PayFast subscription token.");
        }

        return $this->initAPI()->subscriptions->adhoc($locationPaymentToken->token, [
            'amount' => $invoice->rand_amount_in_cents,
            'item_name' => $description,
            'item_description' => "Invoice {$invoice->getKey()}",
        ]);
    }

    public function processItn(): void
    {
        $token = request()->input('token');
        $itemDescription = request()->input('item_description');
        $paymentStatus = trim(htmlspecialchars(request()->input('payment_status')));

        // It must be subscription related
        if (! $token) {
            Log::error('Token not found.');
            exit;
        }

        // It must be the response to an AdHoc subscription hit
        if ($itemDescription === 'AdHoc OnBoarding') {
            $locationPaymentToken = FinancePaymentToken::query()->firstOrCreate(
                ['token' => $token],
                [
                    'location_id' => request()->input('custom_int3'),
                    'payment_gateway_id' => PaymentGateway::PAYFAST,
                    'payment_method' => 'card',
                    'type' => FinancePaymentTokenType::LOCATION,
                ]
            );

            if ($locationPaymentToken->token !== $token) {
                $locationPaymentToken->update(['token' => $token]);
            }
        }

        // It must be the response to an AdHoc invoice hit
        if (strpos($itemDescription, 'Invoice') > -1) {
            preg_match_all('!\d+!', $itemDescription, $invoiceMatches);

            Log::info('Payfast Subscriptions ITN - AdHoc Payment - Debug: '.print_r($_POST, true).'\n'.print_r($invoiceMatches, true));

            $invoiceId = (int) @$invoiceMatches[0][0];

            // Check if invoice ID exists in response
            if ($invoiceId > 0) {
                $locationInvoice = LocationInvoice::find($invoiceId);

                // If no invoice is found, exit
                if (! $locationInvoice) {
                    exit;
                }

                if ($locationInvoice->status == InvoiceStatus::PAID) {
                    exit;
                }

                $payfastPaymentId = request()->get('pf_payment_id');
                $location = Location::query()->find(request()->get('custom_int3'));

                switch ($paymentStatus) {
                    case 'COMPLETE':
                        LocationPayment::query()->create([
                            'facility_invoice_id' => $locationInvoice->getKey(),
                            'amount' => $_POST['amount_gross'],
                            'type' => 'payfast_subscription_adhoc',
                            'box_facility_id' => $location->getKey(),
                            'date_paid' => now(),
                            'reference' => 'PayFast: '.htmlspecialchars($payfastPaymentId),
                            'notes' => "PayFast Subscription Token: $token",
                            'status' => InvoiceStatus::PAID->value,
                        ]);

                        $locationInvoice->update([
                            'status' => InvoiceStatus::PAID->value,
                        ]);
                        break;
                    case 'FAILED':
                        $locationInvoice->update([
                            'status' => InvoiceStatus::FAILED->value,
                        ]);
                        break;
                    case 'PENDING':
                        $locationInvoice->update([
                            'status' => InvoiceStatus::PENDING->value,
                        ]);
                        break;
                    case 'CANCELLED':
                        $locationInvoice->update([
                            'status' => InvoiceStatus::CANCELLED->value,
                        ]);
                        break;
                }

                $locationInvoice->update([
                    'note' => 'PayFast Transaction Reference: '.htmlspecialchars($payfastPaymentId),
                ]);
            }
        }

        // Check if cancellation of subscription
        if ($paymentStatus == 'CANCELLED') {
            $location = Location::query()->find((int) $_POST['custom_int3']);

            if ($location instanceof Location) {
                $locationPaymentToken = (new PaymentTokenService())->getFinancePaymentToken(
                    paymentMethods: ['card'],
                    financePaymentTokenType: FinancePaymentTokenType::LOCATION,
                    location: $location->getKey(),
                    paymentGateway: PaymentGateway::PAYFAST
                );

                $locationPaymentToken?->delete();
            }
        }
    }
}
