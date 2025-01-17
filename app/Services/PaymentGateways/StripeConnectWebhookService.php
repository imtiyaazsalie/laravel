<?php

namespace App\Services\PaymentGateways;

use App\Enums\FinancePaymentTokenType;
use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Enums\PaymentProcessorTag;
use App\Enums\StripeConnect\StripeConnectPaymentMethodType;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\FinancePaymentToken;
use App\Models\LocationInvoice;
use App\Models\LocationPayment;
use App\Models\LocationPaymentGateway;
use App\Models\Tag;
use App\Models\UserInvoice;
use App\Services\CrmService;
use App\Services\InvoiceService;
use App\Services\TenantUserService;
use App\Services\WidgetService;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use ReflectionException;
use Stripe\Account;
use Stripe\Charge;
use Stripe\Dispute;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Stripe\StripeClient;
use Throwable;

class StripeConnectWebhookService
{
    private StripeClient $stripeClient;

    public function __construct()
    {
        $this->stripeClient = new StripeClient(config('stripe-connect.secretKey'));
    }

    //////////////////////////////////////////
    // Platform webhook functions
    //////////////////////////////////////////

    public function handleWebhook(Request $request): void
    {
        $event = $request->event;

        if (! $event instanceof Event) {
            abort(400, 'Parsed data did not result in an instantiated Stripe Event object.');
        }

        if ($request->has('type')) {
            match ($request->get('type')) {
                Event::TYPE_SETUP_INTENT_SUCCEEDED => $this->handleSetupIntentSucceed($event->data->object),
                Event::TYPE_PAYMENT_INTENT_PROCESSING => $this->handlePaymentIntentProcessing($event->data->object),
                Event::TYPE_PAYMENT_INTENT_SUCCEEDED => $this->handlePaymentIntentSucceed($event->data->object),
                Event::TYPE_PAYMENT_INTENT_PAYMENT_FAILED => $this->handlePaymentIntentFailed($event->data->object),
                default => "$event->type not accounted for.",
            };
        }
    }

    private function handleSetupIntentSucceed(SetupIntent $setupIntent): void
    {
        $paymentMethodType = end($setupIntent->payment_method_types);

        if ($paymentMethodType == StripeConnectPaymentMethodType::IDEAL->value) {
            $intent = $this->stripeClient->setupIntents->retrieve(
                $setupIntent->id,
                ['expand' => ['latest_attempt']]
            );

            $setupIntent->payment_method = $intent->latest_attempt->payment_method_details->ideal->generated_sepa_debit;
            $paymentMethodType = StripeConnectPaymentMethodType::SEPA_DEBIT->value;
        }

        FinancePaymentToken::query()->firstOrCreate(
            ['token' => $setupIntent->payment_method],
            [
                'user_id' => $setupIntent->metadata->user_id ?? null,
                'location_id' => $setupIntent->metadata->location_id ?? null,
                'customer_id' => $setupIntent->customer ?? null,
                'setup_intent_id' => $setupIntent->id ?? null,
                'payment_gateway_id' => PaymentGateway::STRIPE_CONNECT,
                'payment_method' => $paymentMethodType,
                'type' => $setupIntent->metadata->user_id ? FinancePaymentTokenType::USER : FinancePaymentTokenType::LOCATION,
            ]
        );
    }

    private function handlePaymentIntentProcessing(PaymentIntent $paymentIntent): void
    {
        $locationInvoice = $paymentIntent->metadata->location_invoice_id ? LocationInvoice::find($paymentIntent->metadata->location_invoice_id) : null;

        $locationInvoice?->update([
            'status' => InvoiceStatus::SUBMITTED,
        ]);
    }

    private function handlePaymentIntentSucceed(PaymentIntent $paymentIntent): void
    {
        $locationInvoice = $paymentIntent->metadata->location_invoice_id ? LocationInvoice::find($paymentIntent->metadata->location_invoice_id) : null;

        if (! $locationInvoice || $locationInvoice->status === InvoiceStatus::PAID->value) {
            return;
        }

        LocationPayment::query()->create([
            'facility_invoice_id' => $locationInvoice->getKey(),
            'amount' => bcdiv($paymentIntent->amount, 100, 2),
            'type' => 'stripe_subscription_adhoc',
            'box_facility_id' => $locationInvoice->location_id,
            'date_paid' => now(),
            'reference' => 'Stripe payment intent id: '.$paymentIntent->id,
            'notes' => 'Stripe payment method id: '.$paymentIntent->payment_method,
            'status' => InvoiceStatus::PAID,
        ]);

        $locationInvoice->update([
            'status' => InvoiceStatus::PAID->value,
            'note' => 'Stripe payment intent id: '.$paymentIntent->id,
        ]);
    }

    private function handlePaymentIntentFailed(PaymentIntent $paymentIntent): void
    {
        $locationInvoice = $paymentIntent->metadata->location_invoice_id ? LocationInvoice::find($paymentIntent->metadata->location_invoice_id) : null;

        $locationInvoice?->update([
            'status' => InvoiceStatus::FAILED,
        ]);
    }

    //////////////////////////////////////////
    // Connected account webhook functions
    //////////////////////////////////////////

    public function handleConnectedWebhook(Request $request): void
    {
        $event = $request->event;

        if (! $event instanceof Event) {
            abort(400, 'Parsed data did not result in an instantiated Stripe Event object.');
        }

        if ($request->has('type')) {
            match ($request->get('type')) {
                // Commented out for now because this spams the user during onboarding.
                // Event::TYPE_ACCOUNT_UPDATED => $this->handleConnectedAccountUpdatedEvent($event->data->object),
                Event::TYPE_SETUP_INTENT_SUCCEEDED => $this->handleConnectedSetupIntentSucceed($event->account, $event->data->object),
                Event::TYPE_PAYMENT_INTENT_CREATED => $this->handleConnectedPaymentIntentCreated($event->account, $event->data->object),
                Event::TYPE_PAYMENT_INTENT_PROCESSING => $this->handleConnectedPaymentIntentProcessing($event->data->object),
                Event::TYPE_PAYMENT_INTENT_SUCCEEDED => $this->handleConnectedPaymentIntentSucceed($event->data->object),
                Event::TYPE_PAYMENT_INTENT_PAYMENT_FAILED => $this->handleConnectedPaymentIntentFailed($event->data->object),
                Event::TYPE_CHARGE_REFUNDED => $this->handleConnectedChargeRefunded($event->data->object),
                Event::TYPE_CHARGE_DISPUTE_CREATED => $this->handleConnectedChargeDisputeCreated($event->data->object),
                Event::TYPE_CHARGE_DISPUTE_CLOSED => $this->handleConnectedChargeDisputeClosed($event->data->object),
                default => "$event->type not accounted for.",
            };
        }
    }

    private function handleConnectedAccountUpdatedEvent(Account $account): void
    {
        $locationPaymentGateway = LocationPaymentGateway::query()
            ->where('payment_gateway_id', '=', PaymentGateway::STRIPE_CONNECT)
            ->where('context', '=', PaymentGatewayContext::DEBIT_ORDER)
            ->whereHas('settings', function ($query) use ($account) {
                $query->where('connected_account_id', '=', $account->id);
            })
            ->orderBy('facility_to_payment_gateway_id', 'desc')
            ->first();

        if (! $locationPaymentGateway) {
            return;
        }

        $subject = null;
        $requirements = $account->requirements;
        $location = $locationPaymentGateway->location;

        if (! $account->payouts_enabled && $account->charges_enabled) {
            $subject = 'Stripe Connect Account requires updates. Payouts were paused.';
        } elseif (! $account->charges_enabled && $account->payouts_enabled) {
            $subject = 'Stripe Connect Account requires updates. Charges were paused.';
        } elseif ($requirements['currently_due'] || $requirements['eventually_due']) {
            $subject = 'This account may have more requirements due in the future.';
        } elseif ($requirements['past_due'] || (! $account->charges_enabled && ! $account->payouts_enabled)) {
            $subject = 'Stripe Connect Account requires updates. Charges and payouts were paused.';
        }

        if ($subject) {
            $headCoaches = (new TenantUserService())->getTenantUsersBy($location->tenant, [UserType::HEAD_COACH], null, [UserStatus::ACTIVE->value]);

            foreach ($headCoaches as $headCoach) {
                $content = Markdown::parse(view('emails.stripe-connect.account-update', [
                    'user' => $headCoach->user->full_name,
                    'location' => $location->name,
                ]))->__toString();

                (new CrmService())->createScheduledEmail($content, $subject, $headCoach->user->email);
            }
        }
    }

    private function handleConnectedSetupIntentSucceed($account, SetupIntent $setupIntent): void
    {
        if (! $setupIntent->metadata->user_id) {
            return;
        }

        $paymentMethodType = end($setupIntent->payment_method_types);

        if ($paymentMethodType == StripeConnectPaymentMethodType::IDEAL->value) {
            $intent = $this->stripeClient->setupIntents->retrieve(
                $setupIntent->id,
                ['expand' => ['latest_attempt']],
                ['stripe_account' => $account]
            );

            $setupIntent->payment_method = $intent->latest_attempt->payment_method_details->ideal->generated_sepa_debit;
            $paymentMethodType = StripeConnectPaymentMethodType::SEPA_DEBIT->value;
        }

        FinancePaymentToken::query()->firstOrCreate(
            ['token' => $setupIntent->payment_method],
            [
                'user_id' => (int) $setupIntent->metadata->user_id,
                'location_id' => $setupIntent->metadata->location_id ?? null,
                'customer_id' => $setupIntent->customer ?? null,
                'setup_intent_id' => $setupIntent->id ?? null,
                'payment_gateway_id' => PaymentGateway::STRIPE_CONNECT->value,
                'payment_method' => $paymentMethodType,
                'type' => $setupIntent->metadata->user_id ? FinancePaymentTokenType::USER : FinancePaymentTokenType::LOCATION,
            ]
        );

        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($setupIntent->metadata->user_id, $setupIntent->metadata->tenant_id);

        if ($tenantUser && $tenantUser->debit_status === UserDebitStatus::DEBIT_ORDER && $tenantUser->status === UserStatus::PENDING) {
            $tenantUser->fresh()->update([
                'user_status_id' => UserStatus::ACTIVE,
                'activated_on' => now(),
            ]);
        }
    }

    private function handleConnectedPaymentIntentCreated($account, PaymentIntent $paymentIntent): void
    {
        try {
            $applicationFee = (new StripeConnectService())->calculateApplicationFee($paymentIntent->payment_method_types[0], bcdiv($paymentIntent->amount, 100, 2));

            $this->stripeClient->paymentIntents->update(
                $paymentIntent->id,
                ['application_fee_amount' => $applicationFee],
                ['stripe_account' => $account]
            );
        } catch (\Exception|ApiErrorException $e) {
            Log::error($e->getMessage());
        }
    }

    private function handleConnectedPaymentIntentProcessing(PaymentIntent $paymentIntent): void
    {
        $invoice = $paymentIntent->metadata->invoice_id ? UserInvoice::find($paymentIntent->metadata->invoice_id) : null;

        if (! $invoice) {
            return;
        }

        $invoice->update([
            'status' => InvoiceStatus::SUBMITTED,
        ]);
    }

    private function handleConnectedPaymentIntentSucceed(PaymentIntent $paymentIntent): void
    {
        $invoice = $paymentIntent->metadata->invoice_id ? UserInvoice::find($paymentIntent->metadata->invoice_id) : null;

        if (! $invoice) {
            return;
        }

        $paymentType = $invoice->userBatch ? InvoicePaymentType::DEBIT_ORDER : InvoicePaymentType::ADHOC;
        $tagIds = [Tag::query()->paymentTag(PaymentProcessorTag::STRIPE_CONNECT)->first()->getKey()];

        (new InvoiceService)->createPaymentForInvoice($invoice, $paymentType, bcdiv($paymentIntent->amount, 100, 2), null, $paymentIntent->id, $tagIds);
    }

    private function handleConnectedPaymentIntentFailed(PaymentIntent $paymentIntent): void
    {
        $invoice = $paymentIntent->metadata->invoice_id ? UserInvoice::find($paymentIntent->metadata->invoice_id) : null;

        if (! $invoice) {
            return;
        }

        $invoice->update([
            'status' => InvoiceStatus::UNPAID,
            'last_status_change_reason' => '"Unpaid" status returned from Stripe-connect: '.$paymentIntent->last_payment_error->message,
        ]);

        if ($invoice->discriminator === InvoiceDiscriminator::SIGN_UP_INVOICE) {
            $locationUser = $invoice->userLocation;

            (new WidgetService())->completeSignUp($locationUser->user->getKey(), $locationUser->location->tenant->getKey(), false);
        } elseif ($invoice->discriminator === InvoiceDiscriminator::DROP_IN_INVOICE) {
            (new WidgetService())->completeDropIn($invoice, false);
        }
    }

    private function handleConnectedChargeRefunded(Charge $charge): void
    {
        $invoice = $charge->metadata->invoice_id ? UserInvoice::find($charge->metadata->invoice_id) : null;

        if (! $invoice) {
            return;
        }

        $amountRefundInCents = $charge->amount_refunded;
        $status = $amountRefundInCents < $invoice->amount_in_cents ? InvoiceStatus::PARTIALLY_REFUNDED : InvoiceStatus::REFUNDED;

        $invoice->payments()->create([
            'reference' => $charge->id,
            'user_to_facility_id' => $invoice->user_to_facility_id,
            'amount' => -bcdiv($amountRefundInCents, 100, 2),
            'currency' => $charge->currency,
            'date_time' => now(),
            'type' => InvoicePaymentType::REFUND,
        ]);

        $invoice->update([
            'status' => $status,
            'last_status_change_reason' => "'{$status->toString()}' status returned from Stripe: ".$charge->description,
        ]);
    }

    private function handleConnectedChargeDisputeCreated(Dispute $dispute): void
    {
        try {
            $crmService = resolve(CrmService::class);
            $invoice = UserInvoice::find($dispute->payment_intent, 'gateway_payment_id');

            if (! $invoice) {
                return;
            }

            $location = $invoice->invoice_location;
            $headCoaches = (new TenantUserService())->getTenantUsersBy($location->tenant, [UserType::HEAD_COACH], null, [UserStatus::ACTIVE->value]);

            foreach ($headCoaches as $headCoach) {
                $content = Markdown::parse(
                    view('emails.stripe-connect.charge-disputed-created', [
                        'ownerName' => $headCoach->user->full_name,
                        'memberName' => $invoice ? $invoice->invoice_member_name : $dispute->evidence?->customer_name,
                        'memberEmail' => $invoice ? $invoice->invoice_email : $dispute->evidence?->customer_email_address,
                        'date' => Carbon::createFromTimestamp($dispute->created, new DateTimeZone($location->timezone ? $location->timezone->zone : $location->tenant->timezone->zone)),
                        'currency' => str($dispute->currency)->upper(),
                        'amount' => $dispute->amount / 100,
                        'reason' => str($dispute->reason)->title()->replace('_', ' '),
                        'status' => str($dispute->status)->title()->replace('_', ' '),
                        'link' => str(config('octiv.web_app_url'))->append('/accounts/stripe-payouts'),
                    ])->render()
                )->__toString();

                $crmService->createScheduledEmail(
                    content: $content,
                    subject: 'Octiv - Notice of Dispute Logged Against Recent Charge',
                    to: $headCoach->user->email,
                    replyTo: $crmService->getReplyTo($location),
                    tenant: $location->tenant,
                    location: $location,
                    queue: 'high'
                );
            }
        } catch (ReflectionException|Throwable $e) {
            Log::error($e->getMessage());
        }
    }

    private function handleConnectedChargeDisputeClosed(Dispute $dispute): void
    {
        try {
            $crmService = resolve(CrmService::class);
            $invoice = UserInvoice::where('gateway_payment_id', '=', $dispute->payment_intent)->first();

            if (! $invoice) {
                return;
            }

            $location = $invoice->invoice_location;
            $headCoaches = (new TenantUserService())->getTenantUsersBy($location->tenant, [UserType::HEAD_COACH], null, [UserStatus::ACTIVE->value]);

            // Early return for unsupported dispute statuses
            if (! in_array($dispute->status, [Dispute::STATUS_WON, Dispute::STATUS_LOST])) {
                return;
            }

            $emailTemplate = $dispute->status === Dispute::STATUS_WON ? 'emails.stripe-connect.charge-disputed-won' : 'emails.stripe-connect.charge-disputed-lost';
            $emailSubject = $dispute->status === Dispute::STATUS_WON ? 'Octiv - Dispute Resolution: Dispute Closed in Your Favor' : 'Octiv - Dispute Resolution: Dispute Closed, Funds Refunded to Client';

            // Use collection methods for readability
            $headCoaches->each(function ($headCoach) use ($crmService, $location, $invoice, $dispute, $emailTemplate, $emailSubject) {
                $content = Markdown::parse(
                    view($emailTemplate, [
                        'ownerName' => $headCoach->user->full_name,
                        'memberName' => $invoice ? $invoice->invoice_member_name : $dispute->evidence?->customer_name,
                        'memberEmail' => $invoice ? $invoice->invoice_email : $dispute->evidence?->customer_email_address,
                        'date' => Carbon::createFromTimestamp($dispute->created, new DateTimeZone($location->timezone ? $location->timezone->zone : $location->tenant->timezone->zone)),
                        'currency' => str($dispute->currency)->upper(),
                        'amount' => $dispute->amount / 100,
                        'reason' => str($dispute->reason)->title()->replace('_', ' '),
                        'status' => str($dispute->status)->title()->replace('_', ' '),
                        'link' => str(config('octiv.web_app_url'))->append('/accounts/stripe-payouts'),
                    ])->render()
                )->__toString();

                $crmService->createScheduledEmail(
                    content: $content,
                    subject: $emailSubject,
                    to: $headCoach->user->email,
                    replyTo: $crmService->getReplyTo($location),
                    tenant: $location->tenant,
                    location: $location,
                    queue: 'high'
                );
            });

            // Handle specific actions for STATUS_LOST
            if ($dispute->status === Dispute::STATUS_LOST) {
                $invoice->payments()->update([
                    'notes' => "Dispute ($dispute->id) lost: $dispute->reason",
                    'deleted' => true,
                ]);

                $invoice->update([
                    'status' => InvoiceStatus::UNPAID,
                ]);
            }

        } catch (ReflectionException|Throwable $e) {
            Log::error($e->getMessage());
        }
    }
}
