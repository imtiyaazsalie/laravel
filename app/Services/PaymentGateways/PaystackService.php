<?php

namespace App\Services\PaymentGateways;

use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentProcessorTag;
use App\Http\Resources\UserInvoiceResource;
use App\Models\Location;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\Tag;
use App\Models\UserInvoice;
use App\Services\CrmService;
use App\Services\InvoiceService;
use App\Services\NonceService;
use App\Services\WidgetService;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    private function getClient(): PendingRequest
    {
        return Http::paystack();
    }

    public function listBanksByCountry(string $country): array
    {
        $response = $this->getClient()->get('/bank', ['country' => $country]);

        $responseData = $response->json();

        if ($response->failed()) {
            abort($response->status(), $responseData['message'] ?? $response->reason());
        }

        if (! in_array('data', $responseData)) {
            return [];
        }

        $banks = collect($responseData['data'])->map(function ($bank) {
            return [
                'name' => $bank['name'],
                'code' => $bank['code'],
                'country' => $bank['country'],
                'currency' => $bank['currency'],
            ];
        });

        return $banks->toArray();
    }

    public function validateAccount(string $accountName, string $accountNumber, string $accountType, string $bankCode, string $documentType, string $documentNumber): array
    {
        $response = $this->getClient()->acceptJson()->post('/bank/validate', [
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'account_type' => $accountType,
            'bank_code' => $bankCode,
            'country_code' => 'ZA',
            'document_type' => $documentType,
            'document_number' => $documentNumber,
        ]);

        $responseData = $response->json();

        if ($response->failed()) {
            if (isset($responseData['data']) && $responseData['data']['verificationMessage']) {
                $errorMessage = $responseData['data']['verificationMessage'];
            } elseif (isset($responseData['message'])) {
                $errorMessage = $responseData['message'];
            } else {
                $errorMessage = $response->reason();
            }

            abort($response->status(), $errorMessage);
        }

        return $responseData['data'];
    }

    public function fetchSubaccount($idOrCode, ?bool $isIncludePercentageCharge = false): ?array
    {
        $response = $this->getClient()->get("/subaccount/$idOrCode");

        $responseData = $response->json();

        $data = [
            'sub_account_id' => $responseData['data']['id'] ?? null,
            'sub_account_code' => $responseData['data']['subaccount_code'] ?? null,
            'bank_name' => $responseData['data']['settlement_bank'] ?? null,
            'account_number' => $responseData['data']['account_number'] ?? null,
            'primary_contact_email' => $responseData['data']['primary_contact_email'] ?? null,
        ];

        if ($isIncludePercentageCharge) {
            $data['percentage_charge'] = $responseData['data']['percentage_charge'] ?? null;
        }

        return $data;
    }

    public function createSubaccount(Location $location, string $bankCode, string $accountNumber, ?string $primaryContactEmail = null): array
    {
        $response = $this->getClient()->acceptJson()->post('/subaccount', [
            'business_name' => $location->tenant->name.' - '.$location->name,
            'settlement_bank' => $bankCode,
            'account_number' => $accountNumber,
            'primary_contact_email' => $primaryContactEmail,
            'percentage_charge' => 0.7,
            'metadata' => ['locationId' => $location->getKey()],
        ]);

        $responseData = $response->json();

        if ($response->failed()) {
            abort($response->status(), $responseData['message'] ?? $response->reason());
        }

        return [
            'sub_account_id' => $responseData['data']['id'],
            'sub_account_code' => $responseData['data']['subaccount_code'],
        ];
    }

    public function updateSubaccount($idOrCode, string $bankCode, string $accountNumber, ?string $primaryContactEmail = null, ?float $percentageCharge = 0): array
    {
        // Check if the subaccount exists
        $existingSubaccountResponse = $this->getClient()->get("/subaccount/$idOrCode");
        $existingSubaccountResponseData = $existingSubaccountResponse->json();

        if ($existingSubaccountResponseData['status'] === false) {
            abort($existingSubaccountResponse->status(), $existingSubaccountResponseData['message'] ?? $existingSubaccountResponse->reason());
        }

        $response = $this->getClient()->acceptJson()->put("/subaccount/$idOrCode", [
            'settlement_bank' => $bankCode,
            'account_number' => $accountNumber,
            'primary_contact_email' => $primaryContactEmail,
            'percentage_charge' => $percentageCharge,
        ]);

        $responseData = $response->json();

        if ($response->failed()) {
            abort($response->status(), $responseData['message'] ?? $response->reason());
        }

        return [
            'sub_account_id' => $responseData['data']['id'],
            'sub_account_code' => $responseData['data']['subaccount_code'],
        ];
    }

    public function updateSubaccountPercentageCharge($idOrCode, float $percentageCharge): array
    {
        // Check if the subaccount exists
        $existingSubaccountResponse = $this->getClient()->get("/subaccount/$idOrCode");
        $existingSubaccountResponseData = $existingSubaccountResponse->json();

        if ($existingSubaccountResponseData['status'] === false) {
            abort($existingSubaccountResponse->status(), $existingSubaccountResponseData['message'] ?? $existingSubaccountResponse->reason());
        }

        $response = $this->getClient()->acceptJson()->put("/subaccount/$idOrCode", [
            'percentage_charge' => $percentageCharge,
        ]);

        $responseData = $response->json();

        if ($response->failed()) {
            abort($response->status(), $responseData['message'] ?? $response->reason());
        }

        return [
            'sub_account_id' => $responseData['data']['id'],
            'sub_account_code' => $responseData['data']['subaccount_code'],
        ];
    }

    public function initializeTransaction(UserInvoice $invoice, LocationPaymentGatewaySettings $paystackSettings)
    {
        $response = $this->getClient()->acceptJson()->post('/transaction/initialize', [
            'amount' => $invoice->amount_in_cents,
            'email' => $invoice->invoice_email,
            'subaccount' => $paystackSettings->sub_account_code,
            'currency' => 'ZAR',
            'reference' => (new NonceService())->generateNonce($invoice->getKey()),
            'callback_url' => config('octiv.web_app_url').'/payment/'.$invoice->getKey().'?success=true',
            'bearer' => 'subaccount',
            'metadata' => [
                'cancel_action' => config('octiv.web_app_url').'/payment/'.$invoice->getKey().'?gid='.$paystackSettings->locationPaymentGateway->getKey(),
                'custom_fields' => [
                    [
                        'display_name' => 'Invoice User Name',
                        'variable_name' => 'invoice_user_name',
                        'value' => $invoice->invoice_member_name,
                    ],
                    [
                        'display_name' => 'Invoice Description',
                        'variable_name' => 'invoice_description',
                        'value' => $invoice->description,
                    ],
                    [
                        'display_name' => 'Invoice Code',
                        'variable_name' => 'invoice_code',
                        'value' => $invoice->code,
                    ],
                    [
                        'display_name' => 'Invoice ID',
                        'variable_name' => 'invoice_id',
                        'value' => $invoice->getKey(),
                    ],
                ],
            ],
        ]);

        $responseData = $response->json();

        if ($response->failed()) {
            abort($response->status(), $responseData['message'] ?? $response->reason());
        }

        $invoice->update([
            'gateway_payment_id' => $responseData['data']['access_code'],
            'gateway_notes' => json_encode($responseData['data']),
        ]);

        return $responseData['data']['authorization_url'];
    }

    public function verifyTransaction(string $reference): UserInvoiceResource
    {
        $response = $this->getClient()->get("/transaction/verify/$reference");

        $responseData = $response->json();

        if ($response->failed()) {
            abort($response->status(), $responseData['message'] ?? $response->reason());
        }

        $invoice = UserInvoice::query()->findOrFail((new NonceService())->getInvoiceIdFromNonce($reference));

        $this->handleChargeSucceeded($responseData['data']);

        return new UserInvoiceResource($invoice->refresh());
    }

    public function fetchSettlements($subaccountId, $from = null, $to = null): array
    {
        $response = $this->getClient()->get('/settlement', [
            'subaccount' => $subaccountId,
            'perPage' => 10000,
            'from' => $from,
            'to' => $to,
        ]);

        $responseData = $response->json();

        if ($response->failed()) {
            abort($response->status(), $responseData['message'] ?? $response->reason());
        }

        $settlements = collect($response['data'])->map(function ($settlement) {
            return [
                'id' => $settlement['id'],
                'status' => $settlement['status'],
                'currency' => $settlement['currency'],
                'totalAmount' => $settlement['total_amount'],
                'settlementDate' => $settlement['settlement_date'],
            ];
        });

        return $settlements->toArray();
    }

    public function listTransactionsBySettlementId($settlementId, $from = null, $to = null): array
    {
        $response = $this->getClient()->get('/transaction', [
            'subaccount_settlement' => $settlementId,
            'perPage' => 1000,
            'from' => $from,
            'to' => $to,
        ]);

        $responseData = $response->json();

        if ($response->failed()) {
            abort($response->status(), $responseData['message'] ?? $response->reason());
        }

        $transactions = [];

        foreach ($responseData['data'] as $transaction) {
            $amount = $transaction['amount'];
            $fees = 0;

            if ($feesSplit = json_decode($transaction['fees_split'])) {
                $fees = $feesSplit->paystack + $feesSplit->integration;
            }

            $transactions[] = [
                'paidAt' => $transaction['paid_at'],
                'channel' => $transaction['channel'],
                'amount' => $amount,
                'fees' => $fees,
                'totalAmount' => $amount - $fees,
                'currency' => $transaction['currency'],
                'gatewayResponse' => $transaction['gateway_response'],
                'status' => $transaction['status'],
                'reference' => $transaction['reference'],
                'metadata' => $transaction['metadata'],
            ];
        }

        return $transactions;
    }

    public function processEvents(Request $request): void
    {
        try {
            switch ($request->get('event')) {
                case 'charge.success':
                    $this->handleChargeSucceeded($request->get('data'));
                    break;
                default:
                    throw new Exception('Event not handled by webhook logic.');
            }
        } catch (Exception $ex) {
            $class = get_class($ex);
            Log::error("Paystack event exception: ($class) {$ex->getMessage()}");
        }
    }

    private function handleChargeSucceeded($data): void
    {
        $invoice = UserInvoice::query()->findOrFail((new NonceService())->getInvoiceIdFromNonce($data['reference']));

        if ($data['status'] !== 'success') {
            $invoice->update([
                'status' => InvoiceStatus::UNPAID,
                'last_status_change_reason' => '"Unpaid" status returned from Paystack.',
            ]);

            if ($invoice->discriminator === InvoiceDiscriminator::SIGN_UP_INVOICE) {
                $locationUser = $invoice->userLocation;

                (new WidgetService())->completeSignUp($locationUser->user->getKey(), $locationUser->location->tenant->getKey(), false);
            } elseif ($invoice->discriminator === InvoiceDiscriminator::DROP_IN_INVOICE) {
                (new WidgetService())->completeDropIn($invoice, false);
            }
        } else {
            if ($invoice->isPaid()) {
                abort(400, "Invoice with ID {$invoice->getKey()} has already been paid.");
            }

            $amountInRands = $data['amount'] / 100;
            $reference = 'Paystack webhook id: '.$data['id'];

            if ($payment = $invoice->payments->where('reference', $reference)->first()) {
                $payment->amount = $amountInRands;
                $payment->date_time = now();
                $payment->reference = $reference;
                $payment->save();

                $invoice->status = $invoice->outstanding_amount <= 0 ? InvoiceStatus::PAID->value : InvoiceStatus::UNPAID->value;
                $invoice->save();
            } else {
                $tagIds = [Tag::query()->paymentTag(PaymentProcessorTag::PAYSTACK)->first()->getKey()];

                (new InvoiceService())->createPaymentForInvoice($invoice, InvoicePaymentType::ADHOC, $amountInRands, null, $reference, $tagIds);
            }

            // Send email to coach
            $subaccountData = $data['subaccount'] ?? null;

            if ($subaccountData && $subaccountData['primary_contact_email'] && $subaccountData['primary_contact_email'] !== '') {
                $content = Markdown::parse(
                    view('emails.paystack.payment-success', [
                        'memberName' => $invoice->invoice_member_name,
                        'amount' => $data['currency'].$amountInRands,
                        'invoiceCode' => $invoice->code,
                        'invoiceDescription' => $invoice->description,
                    ])
                )->__toString();

                (new CrmService())->createScheduledEmail(
                    content: $content,
                    subject: 'Payment successful',
                    to: $subaccountData['primary_contact_email'],
                    queue: 'high'
                );
            }
        }
    }
}
