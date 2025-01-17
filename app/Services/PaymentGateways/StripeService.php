<?php

namespace App\Services\PaymentGateways;

use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\MandateStatus;
use App\Enums\TagType;
use App\Models\DebitBatch;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\StripeMandate;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\UserBatch;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Models\UserInvoicePayment;
use App\Services\InvoiceService;
use App\Services\UserBatchService;
use App\Services\WidgetService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Card;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Mandate;
use Stripe\Source;
use Stripe\Stripe;

class StripeService
{
    private const ACCEPTABLE_EVENT_TYPES = [
        'checkout.session.completed',
        'customer.source.updated',
        'charge.failed',
        'charge.refunded',
        'charge.succeeded',
        'customer.deleted',
    ];

    public function submitDebitBatch(DebitBatch $debitBatch, ?Carbon $chargeDateOverride = null): void
    {
        $userBatches = (new UserBatchService())->getUserBatchesForDebitBatchSubmission($debitBatch);

        $debitBatch->startLogEntry();

        if ($userBatches->isEmpty()) {
            $debitBatch->log("No user batches found for {$debitBatch->location->name}'s batch: {$debitBatch->getKey()}.")->endLogEntry();
            $debitBatch->save();
            throw new Exception('Debit batch has no user batches for submission.');
        }

        /** @var UserBatch $userBatch */
        foreach ($userBatches as $userBatch) {
            $invoice = $userBatch->invoice;

            if ($invoice->isPaid()) {
                $debitBatch->log('Invoice has already been marked as paid '.$userBatch->user->full_name.' (invoice '.$invoice->getKey().' ).');

                continue;
            }

            $debitBatch->log('Processing '.$userBatch->user->full_name.' (invoice '.$invoice->getKey().' ).');

            if ($chargeDateOverride instanceof Carbon) {
                $invoice->update(['due_on' => $chargeDateOverride]);
            }

            $paymentId = $this->requestPaymentForInvoice($userBatch->invoice);

            if ($paymentId) {
                $debitBatch->log("Payment successful (payment ID = $paymentId).");

                $invoice->update([
                    'status' => InvoiceStatus::SUBMITTED,
                    'gateway_payment_id' => $paymentId,
                ]);
            }
        }

        // Mark the batch as processed
        $debitBatch->fill([
            'is_processed' => true,
            'dt_processed' => now(),
        ]);

        $debitBatch->endLogEntry();
        $debitBatch->save();
    }

    public function requestPaymentForInvoice(UserInvoice $invoice): ?string
    {
        if (! $invoice->locationUser) {
            return null;
        }

        try {
            $metaData = ['invoice_id' => $invoice->getKey()];

            $settings = LocationPaymentGatewaySettings::query()
                ->joinRelationship('locationPaymentGateway')
                ->where('box_facility_to_payment_gateway.box_facility_id', '=', $invoice->locationUser->location_id)
                ->whereNotNull('facility_payment_gateway_settings.token')
                ->whereNotNull('facility_payment_gateway_settings.public_token')
                ->where('facility_payment_gateway_settings.token', '!=', '')
                ->where('facility_payment_gateway_settings.public_token', '!=', '')
                ->where('box_facility_to_payment_gateway.is_active', '=', true)
                ->first();

            if (! $settings) {
                throw new Exception('The facility linked to this user has no active Stripe payment gateway settings.');
            }

            $mandate = StripeMandate::query()
                ->where('user_id', '=', $invoice->locationUser->user_id)
                ->where('facility_payment_gateway_id', '=', $settings->getKey())
                ->where('status', '=', 'active')
                ->first();

            if (! $mandate) {
                throw new Exception('User does not have an active mandate for payment.');
            }

            $currency = strtoupper($invoice->locationUser->location->tenant->memberCurrency->code);

            if (! empty($mandate->bank_account) && ! empty($mandate->bank_account_verified_at)) {
                $charge = Charge::create([
                    'amount' => $invoice->amount_in_cents,
                    'currency' => $currency,
                    'description' => "Octiv invoice $invoice->code",
                    'metadata' => $metaData,
                    'customer' => $mandate->customer,
                    'source' => $mandate->bank_account,
                ]);
            } else {
                $charge = Charge::create([
                    'amount' => $invoice->amount_in_cents,
                    'currency' => $currency,
                    'description' => "Octiv invoice $invoice->code",
                    'metadata' => $metaData,
                    'customer' => $mandate->customer,
                ]);
            }

            return $charge->id;
        } catch (ApiErrorException|Exception $ex) {
            $timeStamp = date('Y-m-d H:i');

            $invoice->gateway_notes = "$timeStamp: Error processing payment with reason: {$ex->getMessage()}";

            $invoice->save();

            return null;
        }
    }

    public function createCheckOutSession(UserInvoice $invoice, LocationPaymentGatewaySettings $settings): ?string
    {
        $invoiceLineItemDescription = implode(', ', $invoice->invoiceItems()->get()->map(function (UserInvoiceItem $invoiceItem) {
            return $invoiceItem->description;
        })->toArray());

        $invoiceLineItems[] = [
            'price_data' => [
                'currency' => $invoice->currency,
                'product_data' => [
                    'name' => $invoiceLineItemDescription,
                ],
                'unit_amount' => $invoice->amount_in_cents,
            ],
            'quantity' => 1,
        ];

        try {
            // Get web app url
            $webAppUrl = config('octiv.web_app_url').'/payment/'.$invoice->getKey();
            $secretKey = app()->environment('local') ? config('octiv.stripe.secret_key') : $settings->secret_key;

            Stripe::setApiKey($secretKey);

            $session = Session::create([
                'customer_email' => $invoice->invoice_email,
                'payment_method_types' => ['card'],
                'line_items' => $invoiceLineItems,
                'mode' => 'payment',
                'success_url' => $webAppUrl.'?success=true',
                'cancel_url' => $webAppUrl.'?gid='.$settings->location_payment_gateway_id,
                'client_reference_id' => $invoice->getKey(),
            ]);

            return $session->id;
        } catch (ApiErrorException $e) {
            abort(400, $e->getMessage());
        }
    }

    public function handleWebhook(Request $request): void
    {
        $decodedEvents = json_decode($request->getContent(), true);
        $event = Event::constructFrom($decodedEvents);

        if (! $event instanceof Event) {
            abort(400, 'Parsed data did not result in an instantiated Stripe Event object.');
        }

        if (! in_array($event->type, self::ACCEPTABLE_EVENT_TYPES)) {
            abort(400, "$event->type not accounted for.");
        }

        $eventObject = $event->data->object;

        if ($request->has('type')) {
            match ($request->get('type')) {
                'checkout.session.completed' => $this->handleCheckOutSessionCompleted($eventObject),
                'customer.source.updated' => $this->handleBankAccountUpdated($eventObject),
                'charge.failed' => $this->handleChargeFailed($eventObject->id),
                'charge.refunded' => $this->handleChargeRefunded($eventObject->id),
                'charge.succeeded' => $this->handleChargeSucceeded($eventObject->id),
                'customer.deleted' => $this->cancelMandateWithReason($eventObject->id, $event->type),
            };
        }
    }

    private function handleCheckOutSessionCompleted(Session $checkoutSession): void
    {
        $invoice = UserInvoice::query()->find($checkoutSession->client_reference_id);

        if (! $invoice instanceof UserInvoice) {
            Log::info("Invoice not found. # $checkoutSession->client_reference_id");

            return;
        }

        if ($checkoutSession->payment_status == 'paid') {
            if ($invoice->status === InvoiceStatus::PAID) {
                Log::info("Invoice with ID {$invoice->getKey()} has already been paid.");

                return;
            }

            $tagIds = null;
            $paymentProcessorTag = Tag::query()
                ->where('name', '=', 'Stripe')
                ->where('type', '=', 'payment_processor')
                ->whereNull('owner_model')
                ->whereNull('owner_model_id')
                ->first();

            if ($paymentProcessorTag instanceof Tag) {
                $tagIds = [$paymentProcessorTag->getKey()];
            }

            $reference = "Stripe checkout session id: $checkoutSession->id";
            (new InvoiceService())->createPaymentForInvoice($invoice, InvoicePaymentType::ADHOC, bcdiv($checkoutSession->amount_total, 100, 2), null, $reference, $tagIds);
        } else {
            $invoice->update([
                'status' => InvoiceStatus::PAID,
                'last_status_change_reason' => '"Unpaid" status returned from Stripe.',
            ]);

            if ($invoice->discriminator === InvoiceDiscriminator::SIGN_UP_INVOICE) {
                (new WidgetService())->completeSignUp($invoice->locationUser->user_id, $invoice->locationUser->location->tenant_id, false);
            } elseif ($invoice->discriminator === InvoiceDiscriminator::DROP_IN_INVOICE) {
                (new WidgetService())->completeDropIn($invoice, false);
            }
        }
    }

    private function handleBankAccountUpdated(Source|Card $source): void
    {
        if ($source->object !== 'bank_account') {
            return;
        }

        $sourceId = $source->id;
        $customerId = $source->customer;
        $sourceStatus = $source->status;

        $mandate = StripeMandate::query()->where('customer_id', $customerId)->first();

        if (! $mandate instanceof StripeMandate) {
            Log::info("A mandate for customer with customer ID $customerId does not exist.");

            return;
        }

        // if this event is about a source that is not the current source ID, don't continue
        // @todo revisit this to ensure we're handling this correctly
        if ($mandate->bank_account !== $sourceId) {
            Log::info("Mandate with ID {$mandate->getKey()} (customer ID $customerId) does not have the associated source ID of $sourceId.");

            return;
        }

        // if the customers mandate is currently active, but the source status is not 'chargeable', change mandate status
        if ($sourceStatus !== 'verified' && $mandate->status === Mandate::STATUS_ACTIVE) {
            $mandate->update([
                'last_status_change_reason' => "Customer's bank account $sourceId is no longer verified with status `$sourceStatus`.",
                'status' => Mandate::STATUS_PENDING,
            ]);

        } // otherwise if "verified", activate the mandate
        elseif ($mandate->status !== Mandate::STATUS_ACTIVE) {
            $mandate->update([
                'last_status_change_reason' => "Customer's bank account $sourceId has been verified.",
                'status' => Mandate::STATUS_ACTIVE,
            ]);

        } else {
            Log::info("Mandate with ID {$mandate->getKey()} (customer ID $customerId) is not actionable. Stripe status: $sourceStatus, mandate status: $mandate->status");
        }
    }

    private function handleChargeFailed(string $chargeId): void
    {
        $invoice = UserInvoice::query()->where('gateway_payment_id', '=', $chargeId)->first();

        if (! $invoice instanceof UserInvoice) {
            Log::info("Invoice with gatewayPaymentId $chargeId does not exist.");

            return;
        }

        $invoice->update([
            'status' => InvoiceStatus::UNPAID,
            'last_status_change_reason' => '"Charge failed" status returned from Stripe.',
        ]);

        if ($invoice->discriminator === InvoiceDiscriminator::SIGN_UP_INVOICE) {
            (new WidgetService())->completeSignUp($invoice->locationUser->user_id, $invoice->locationUser->location->tenant_id, false);
        } elseif ($invoice->discriminator === InvoiceDiscriminator::DROP_IN_INVOICE) {
            (new WidgetService())->completeDropIn($invoice, false);
        }
    }

    private function handleChargeRefunded(string $chargeId): void
    {
        $invoice = UserInvoice::query()->where('gateway_payment_id', '=', $chargeId)->first();

        if (! $invoice instanceof UserInvoice) {
            Log::info("Invoice with gatewayPaymentId $chargeId does not exist.");

            return;
        }

        $invoice->update([
            'status' => InvoiceStatus::UNPAID,
            'last_status_change_reason' => '"Charge refunded" status returned from Stripe.',
        ]);

        foreach ($invoice->payments as $payment) {
            if ($payment->type !== InvoicePaymentType::ADHOC) {
                continue;
            }

            $taggable = Taggable::query()
                ->where('morphable_id', '=', $payment->getKey())
                ->where('morphable_model', '=', (new UserInvoicePayment())->getTable())
                ->where('tag_id', '=', 4)
                ->first();

            if ($taggable instanceof Taggable && $taggable->tag->type === TagType::PAYMENT) {
                $payment->update(['deleted' => true]);
            }
        }
    }

    private function handleChargeSucceeded(string $chargeId): void
    {
        $invoice = UserInvoice::query()->where('gateway_payment_id', '=', $chargeId)->first();

        if (! $invoice instanceof UserInvoice) {
            Log::info("Invoice with gatewayPaymentId $chargeId does not exist.");

            return;
        }

        if ($invoice->status !== InvoiceStatus::SUBMITTED) {
            // @todo - do we want to stop this from continuing if the status is not the expected "submitted"?
            Log::info("Payment event cannot be handled. Invoice with ID {$invoice->getKey()} has an invalid status of '{$invoice->status}', and should be 'submitted'.");

            return;
        }

        if (! ($invoice->locationPaymentGateway?->settings) instanceof LocationPaymentGatewaySettings) {
            Log::info("Appropriate settings for facility {$invoice->invoice_location->getKey()} do not exist.");

            return;
        }

        $tagIds = null;

        $paymentProcessorTag = Tag::query()
            ->where('name', '=', 'Stripe')
            ->where('type', '=', 'payment_processor')
            ->whereNull('owner_model')
            ->whereNull('owner_model_id')
            ->first();

        if ($paymentProcessorTag instanceof Tag) {
            $tagIds = [$paymentProcessorTag->getKey()];
        }

        (new InvoiceService())->createPaymentForInvoice($invoice, InvoicePaymentType::ADHOC, $invoice->amount, null, $chargeId, $tagIds);
    }

    private function cancelMandateWithReason(string $customerId, string $reason): void
    {
        $mandate = StripeMandate::query()->where('customer_id', $customerId)->first();

        if (! $mandate instanceof StripeMandate) {
            Log::info("A mandate for customer with customer ID $customerId does not exist.");

            return;
        }

        if ($mandate->status !== Mandate::STATUS_ACTIVE) {
            Log::info("The mandate associated with customer ID $customerId is not active.");

            return;
        }

        $mandate->update([
            'last_status_change_reason' => $reason,
            'status' => MandateStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
