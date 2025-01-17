<?php

namespace App\Jobs\StripeConnect;

use App\Enums\FinancePaymentTokenType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Models\DebitBatch;
use App\Models\FinancePaymentToken;
use App\Models\UserBatch;
use App\Services\PaymentGateways\StripeConnectService;
use App\Services\PaymentTokenService;
use App\Services\UserBatchService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Stripe\Account;
use Stripe\Exception\ApiErrorException;

class SubmitDebitBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public DebitBatch $debitBatch, public ?Carbon $chargeDateOverride = null)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(StripeConnectService $stripeConnectService): void
    {
        $account = $stripeConnectService->getAccount($this->debitBatch->location);

        if (! $account instanceof Account) {
            throw new Exception("Stripe-connect: Location {$this->debitBatch->location->name} - {$this->debitBatch->location->getKey()} : is not onboarded.");
        }

        if (! $account->charges_enabled) {
            throw new Exception("Stripe-connect: Charges are not enabled for {$this->debitBatch->location->name} - {$this->debitBatch->location->getKey()}.");
        }

        $userBatches = (new UserBatchService())->getUserBatchesForDebitBatchSubmission($this->debitBatch);

        $this->debitBatch->startLogEntry();

        if ($userBatches->isEmpty()) {
            $this->debitBatch->log("No user batches found for {$this->debitBatch->location->name}'s batch: {$this->debitBatch->getKey()}.")->endLogEntry();
            $this->debitBatch->save();
            throw new Exception('Debit batch has no user batches for submission.');
        }

        /** @var UserBatch $userBatch */
        foreach ($userBatches as $userBatch) {
            if (! ($invoice = $userBatch->invoice)) {
                continue;
            }

            $paymentToken = (new PaymentTokenService())->getFinancePaymentToken(
                paymentMethods: $stripeConnectService->getDebitOrderPaymentMethodsForTenant($this->debitBatch->location->tenant),
                financePaymentTokenType: FinancePaymentTokenType::USER,
                user: $userBatch->user,
                location: $this->debitBatch->location,
                paymentGateway: PaymentGateway::STRIPE_CONNECT
            );

            if (! $paymentToken instanceof FinancePaymentToken) {
                $this->debitBatch->log('User('.$userBatch->user->full_name.') does not a payment token for this location: '.$this->debitBatch->location->name.' (invoice '.$invoice->getKey().').');

                continue;
            }

            if ($invoice->isPaid()) {
                $this->debitBatch->log('Invoice has already been marked as paid '.$userBatch->user->full_name.' (invoice '.$invoice->getKey().').');

                continue;
            }

            $this->debitBatch->log('Processing '.$userBatch->user->full_name.' (invoice '.$invoice->getKey().').');

            if ($this->chargeDateOverride instanceof Carbon) {
                $invoice->update(['due_on' => $this->chargeDateOverride]);
            }

            try {
                $paymentIntent = $stripeConnectService->createPaymentIntent([
                    'customer' => $paymentToken->customer_id,
                    'payment_method_types' => [$paymentToken->payment_method],
                    'payment_method' => $paymentToken->token,
                    'amount' => $invoice->amount_in_cents,
                    'currency' => strtoupper($this->debitBatch->location->tenant->memberCurrency->code),
                    'confirm' => true,
                    'off_session' => true,
                    'application_fee_amount' => $stripeConnectService->calculateApplicationFee($paymentToken->payment_method, $invoice->amount),
                    'description' => $invoice->description.' '.$invoice->code,
                    'metadata' => [
                        'invoice_id' => $invoice->getKey(),
                        'user_to_batch_id' => $userBatch->getKey(),
                    ],
                ], [
                    'stripe_account' => $account->id,
                    'idempotency_key' => $invoice->getKey(),
                ]);

                if ($paymentIntent) {
                    $this->debitBatch->log("Payment successful (payment intent ID = $paymentIntent->id).");

                    $invoice->update([
                        'status' => InvoiceStatus::SUBMITTED,
                        'gateway_payment_id' => $paymentIntent->id,
                    ]);
                }
            } catch (ApiErrorException $e) {
                $this->debitBatch->log('Payment intent creation error: '.$userBatch->user->full_name.' (invoice '.$invoice->getKey().'). Error: '.$e->getMessage());
            }
        }

        // Mark the batch as processed
        $this->debitBatch->fill([
            'is_processed' => true,
            'dt_processed' => now(),
        ]);

        $this->debitBatch->endLogEntry();
        $this->debitBatch->save();
    }
}
