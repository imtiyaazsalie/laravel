<?php

namespace App\Services\PaymentGateways;

use App\Enums\GoCardlessWebhookStatus;
use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\MandateStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Enums\PaymentProcessorTag;
use App\Enums\TagType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Exceptions\GoCardless\EventActionNotAccountedFor;
use App\Exceptions\GoCardless\ResourceTypeNotAccountedFor;
use App\Models\DebitBatch;
use App\Models\GoCardlessWebhookEvent;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\MandateGoCardless;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\User;
use App\Models\UserBatch;
use App\Models\UserInvoice;
use App\Models\UserInvoicePayment;
use App\Services\CrmService;
use App\Services\InvoiceService;
use App\Services\LocationService;
use App\Services\TenantUserService;
use App\Services\UserBatchService;
use App\Services\WidgetService;
use Carbon\Carbon;
use Exception;
use GoCardlessPro\Client;
use GoCardlessPro\Core\Exception\InvalidStateException;
use GoCardlessPro\Core\ListResponse;
use GoCardlessPro\Resources\Customer;
use GoCardlessPro\Resources\Event;
use GoCardlessPro\Resources\Payment;
use GoCardlessPro\Resources\Payout;
use GoCardlessPro\Resources\PayoutItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OAuth2\Client as OAuthClient;

class GoCardlessService
{
    private string $goCardlessEnvironment;

    public function __construct()
    {
        $this->goCardlessEnvironment = config('gocardless.environment');
    }

    private function getOAuthClient(): OAuthClient
    {
        return new OAuthClient(config('gocardless.client_id'), config('gocardless.client_secret'));
    }

    private function getConnectUrl(string $suffix = ''): string
    {
        $urls = [
            'live' => "https://connect.gocardless.com/$suffix",
            'sandbox' => "https://connect-sandbox.gocardless.com/$suffix",
        ];

        if (! array_key_exists($this->goCardlessEnvironment, $urls)) {
            throw new InvalidArgumentException("$this->goCardlessEnvironment is not a valid environment, please use one of ".implode(', ', array_keys($urls)));
        }

        return $urls[$this->goCardlessEnvironment];
    }

    public function getClient($token): Client
    {
        return new Client(['access_token' => $token, 'environment' => $this->goCardlessEnvironment]);
    }

    //////////////////////////////////////////
    // Start of merchant service functions
    //////////////////////////////////////////

    public function isLocationOnboarded(Location $location): bool
    {
        return (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)
            ->whereHas('settings', function ($query) {
                $query->whereNotNull('token');
            })
            ->exists();
    }

    public function beginMerchantOnBoardingFlow(Location $location): string
    {
        (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)
            ->firstOr(function () use ($location) {
                $locationPaymentGateway = LocationPaymentGateway::create([
                    'location_id' => $location->getKey(),
                    'context' => PaymentGatewayContext::AD_HOC,
                    'payment_gateway_id' => PaymentGateway::GO_CARDLESS,
                ]);

                LocationPaymentGatewaySettings::create([
                    'location_payment_gateway_id' => $locationPaymentGateway->getKey(),
                    'public_token' => sha1(uniqid(true)),
                ]);
            });

        return $this->getOAuthClient()->getAuthenticationUrl(
            $this->getConnectUrl('oauth/authorize'),
            config('gocardless.merchant_callback_url'),
            [
                'scope' => 'read_write',
                'state' => (new LocationService())->getGoCardlessSettings($location)?->public_token,
                'initial_view' => 'login',
                'prefill' => [
                    'email' => auth()->user()?->email,
                    'given_name' => auth()->user()?->name,
                    'family_name' => auth()->user()?->surname,
                    'organisation_name' => $location->business_name ?? $location->name,
                ],
            ]
        );
    }

    public function completeMerchantOnBoardingFlow(LocationPaymentGatewaySettings $settings, string $code): void
    {
        $response = $this->getOAuthClient()->getAccessToken($this->getConnectUrl('oauth/access_token'), 'authorization_code', [
            'code' => $code,
            'redirect_uri' => config('gocardless.merchant_callback_url'),
        ]);

        if (! isset($response['result']) || ! isset($response['result']['access_token']) || ! isset($response['result']['organisation_id'])) {
            throw new Exception('Response returned from OAuth client was invalid.');
        }

        $settings->update([
            'token' => $response['result']['access_token'],
            'organisation_id' => $response['result']['organisation_id'],
            'disconnected_from_event' => false,
        ]);
    }

    //////////////////////////////////////////
    // Start of mandate service functions
    //////////////////////////////////////////

    public function isUserOnBoard(User $user, Location $location): bool
    {
        return $this->getMandateForUser(user: $user, location: $location, statuses: MandateStatus::activeStatusValues())->count() > 0;
    }

    public function beginUserOnBoardingFlow(User $user, Location $location, ?string $successUrlSuffix = null, ?array $meta = []): string
    {
        if (! $this->isLocationOnboarded($location)) {
            throw new Exception('Location has not yet been on-boarded to GoCardless');
        }

        $locationPaymentGateway = (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)->first();

        $mandate = MandateGoCardless::firstOrCreate([
            'user_id' => $user->getKey(),
            'box_id' => $location->tenant_id,
            'facility_payment_gateway_id' => $locationPaymentGateway->getKey(),
        ]);

        if ($mandate->status === MandateStatus::ACTIVE) {
            throw new Exception('User already has an active mandate.');
        }

        $goCardlessSettings = $locationPaymentGateway->settings;
        $successUrlSuffix = $successUrlSuffix ?? '/payment/gocardless-mandate/callback';

        $redirectFlow = $this->getClient($goCardlessSettings->token)->redirectFlows()->create([
            'params' => [
                'description' => "Automatic membership payments to $location->business_name.",
                'session_token' => (string) $user->getKey(),
                'success_redirect_url' => config('octiv.web_app_url').$successUrlSuffix,
                'prefilled_customer' => [
                    'given_name' => $user->name,
                    'family_name' => $user->surname,
                    'email' => $user->email,
                ],
                'metadata' => empty($meta) === true ? ['hello' => 'world'] : $meta,
            ],
        ]);

        if (! $redirectFlow) {
            throw new Exception('Redirect flow was not created.');
        }

        $mandate->update(['redirect_flow_id' => $redirectFlow->id]);
        $mandate->refresh();

        return $redirectFlow->redirect_url;
    }

    public function completeUserOnBoardingFlow(MandateGoCardless $mandate)
    {
        $client = $this->getClient($mandate->locationPaymentGateway->settings->token);

        $sessionToken = $mandate->user?->getKey();

        if (! $sessionToken) {
            return null;
        }

        $redirectFlow = $client->redirectFlows()->complete($mandate->redirect_flow_id, [
            'params' => [
                'session_token' => (string) $sessionToken,
            ],
        ]);

        $mandate->update([
            'mandate' => $redirectFlow->links->mandate,
            'customer' => $redirectFlow->links->customer,
        ]);

        if (isset($redirectFlow->metadata)) {
            return $redirectFlow->metadata;
        }

        return null;
    }

    public function getMandateForUser(User $user, Location $location, ?array $statuses = null): Builder
    {
        $builder = MandateGoCardless::query()
            ->join('box_facility_to_payment_gateway', 'go_cardless_mandates.facility_payment_gateway_id', '=', 'box_facility_to_payment_gateway.facility_to_payment_gateway_id')
            ->where('box_facility_to_payment_gateway.box_facility_id', $location->getKey())
            ->where('go_cardless_mandates.user_id', $user->getKey())
            ->where('go_cardless_mandates.box_id', $location->tenant_id);

        if ($statuses) {
            $builder->whereIn('go_cardless_mandates.status', $statuses);
        }

        return $builder;
    }

    //////////////////////////////////////////
    // Start of webhook event functions
    //////////////////////////////////////////

    public function processEvent(GoCardlessWebhookEvent $webhookEvent): void
    {
        /** @var Event $event */
        $event = $webhookEvent->payload;

        try {
            switch ($event->resource_type) {
                case 'mandates':
                    $this->processMandateEvent($webhookEvent);
                    break;
                case 'payments':
                    $this->processPaymentEvent($webhookEvent);
                    break;
                default:
                    throw new ResourceTypeNotAccountedFor("Resource type not accommodated. Type: $event->resource_type, Action: $event->action");
            }

            $webhookEvent->update([
                'status' => GoCardlessWebhookStatus::COMPLETED,
                'payload' => null,
                'last_processed_at' => now(),
            ]);
        } catch (ResourceTypeNotAccountedFor|EventActionNotAccountedFor $ex) {
            // Skipped so deleting them.
            $webhookEvent->delete();
        } catch (Exception $ex) {
            $processedCount = $webhookEvent->processed_count + 1;
            $errors = is_array($webhookEvent->errors) ? $webhookEvent->errors : [];
            $errors[] = $ex->getMessage();

            $webhookEvent->update([
                'processed_count' => $processedCount,
                'status' => $processedCount >= config('gocardless.event_processing_failure_threshold') ? GoCardlessWebhookStatus::FAILED : GoCardlessWebhookStatus::ERROR,
                'errors' => $errors,
            ]);
        }
    }

    private function processMandateEvent(GoCardlessWebhookEvent $webhookEvent): void
    {
        $payload = $webhookEvent->payload;

        $action = $payload->action;
        $mandateId = $payload->links->mandate;
        $description = $payload->details->description;

        match ($action) {
            'cancelled' => $this->cancelMandate($mandateId, $description),
            'created' => $this->updateMandateStatus($mandateId, MandateStatus::CREATED, $description),
            'customer_approval_granted' => $this->updateMandateStatus($mandateId, MandateStatus::CUSTOMER_APPROVAL_GRANTED, $description),
            'customer_approval_skipped' => $this->updateMandateStatus($mandateId, MandateStatus::CUSTOMER_APPROVAL_SKIPPED, $description),
            'active' => $this->updateMandateStatus($mandateId, MandateStatus::ACTIVE, $description),
            'failed' => $this->failedMandate($mandateId, $description),
            'transferred' => $this->updateMandateStatus($mandateId, MandateStatus::TRANSFERRED, $description),
            'expired' => $this->expiredMandate($mandateId, $description),
            'submitted' => $this->updateMandateStatus($mandateId, MandateStatus::SUBMITTED, $description),
            'resubmission_requested' => $this->updateMandateStatus($mandateId, MandateStatus::RESUBMISSION_REQUESTED, $description),
            'reinstated' => $this->reinstatedMandate($mandateId, $description),
            default => throw new EventActionNotAccountedFor("Action not accounted for: $action"),
        };
    }

    private function processPaymentEvent(GoCardlessWebhookEvent $webhookEvent): void
    {
        $payload = $webhookEvent->payload;

        match ($payload->action) {
            'paid_out' => $this->handlePaymentPaidOut($payload->links->payment),
            'failed', 'cancelled' => $this->handlePaymentFailedOrCancelled($payload->links->payment, $payload->details->description),
            'charged_back' => $this->handlePaymentChargedBack($payload->links->payment, $payload->details->description),
            default => throw new EventActionNotAccountedFor("Action not accounted for: $payload->action"),
        };
    }

    //////////////////////////////////////////
    // Start of mandate functions
    //////////////////////////////////////////

    private function updateMandateStatus(string $mandateId, MandateStatus $status, string $reason): void
    {
        $mandate = $this->getMandateById($mandateId);

        $mandate->update([
            'status' => $status,
            'note' => $reason.' Received at '.now()->format('Y-m-d H:i:s').'UTC',
            'cancelled_at' => null,
            'cancel_reason' => null,
        ]);
    }

    private function cancelMandate(string $mandateId, string $reason): void
    {
        $mandate = $this->getMandateById($mandateId);

        $mandate->update([
            'status' => MandateStatus::CANCELLED,
            'cancel_reason' => $reason.' Received at '.date('Y-m-d H:i:s').'UTC',
        ]);

        $location = $mandate->locationPaymentGateway?->location ?? null;

        if (! $location) {
            Log::emergency('Could not determine location to cancel GoCardless mandate.', ['mandate_id' => $mandate->getKey()]);

            return;
        }

        $crmService = resolve(CrmService::class);
        $admins = (new TenantUserService())->getTenantUsersBy(tenant: $location->tenant, userTypes: [UserType::HEAD_COACH, UserType::BOX_ADMIN], userStatuses: [UserStatus::ACTIVE]);

        foreach ($admins as $admin) {
            $content = Markdown::parse(
                view('emails.gocardless.mandate-cancelled', [
                    'memberName' => $mandate->user->full_name,
                    'adminName' => $admin->user->full_name,
                    'reason' => $reason,
                ])->render()
            )->__toString();

            $crmService->createScheduledEmail(
                content: $content,
                subject: 'Octiv - Mandate Cancellation',
                to: $admin->user->email,
                replyTo: $crmService->getReplyTo($location),
                tenant: $location->tenant,
                location: $location,
            );
        }
    }

    private function failedMandate(string $mandateId, string $reason): void
    {
        $mandate = $this->getMandateById($mandateId, true);

        $mandate->update([
            'status' => MandateStatus::FAILED,
            'note' => $reason.' Received at '.date('Y-m-d H:i:s').'UTC',
        ]);

        $location = $mandate->locationPaymentGateway?->location ?? null;

        if (! $location) {
            Log::emergency('Could not determine location to cancel GoCardless mandate.', ['mandate_id' => $mandate->getKey()]);

            return;
        }

        $crmService = resolve(CrmService::class);
        $admins = (new TenantUserService())->getTenantUsersBy(tenant: $location->tenant, userTypes: [UserType::HEAD_COACH, UserType::BOX_ADMIN], userStatuses: [UserStatus::ACTIVE]);

        foreach ($admins as $admin) {
            $content = Markdown::parse(
                view('emails.gocardless.mandate-failed', [
                    'memberName' => $mandate->user->full_name,
                    'adminName' => $admin->full_name,
                    'reason' => $reason,
                ])
            )->__toString();

            $crmService->createScheduledEmail(
                content: $content,
                subject: 'Octiv - Mandate Failed',
                to: $admin->user->email,
                replyTo: $crmService->getReplyTo($location),
                tenant: $location->tenant,
                location: $location,
            );
        }
    }

    private function expiredMandate(string $mandateId, string $reason): void
    {
        $mandate = $this->getMandateById($mandateId, true);

        $mandate->update([
            'status' => MandateStatus::EXPIRED,
            'note' => $reason.' Received at '.date('Y-m-d H:i:s').'UTC',
        ]);

        $location = $mandate->locationPaymentGateway?->location ?? null;

        if (! $location) {
            Log::emergency('Could not determine location to cancel GoCardless mandate.', ['mandate_id' => $mandate->getKey()]);

            return;
        }

        $crmService = resolve(CrmService::class);
        $admins = (new TenantUserService())->getTenantUsersBy(tenant: $location->tenant, userTypes: [UserType::HEAD_COACH, UserType::BOX_ADMIN], userStatuses: [UserStatus::ACTIVE]);

        foreach ($admins as $admin) {

            $content = Markdown::parse(
                view('emails.gocardless.mandate-expired', [
                    'memberName' => $mandate->user->full_name,
                    'adminName' => $admin->full_name,
                    'reason' => $reason,
                ])
            )->__toString();

            $crmService->createScheduledEmail(
                content: $content,
                subject: 'Octiv - Mandate has Expired',
                to: $admin->user->email,
                replyTo: $crmService->getReplyTo($location),
                tenant: $location->tenant,
                location: $location,
            );
        }
    }

    private function reinstatedMandate(string $mandateId, string $reason): void
    {
        $mandate = $this->getMandateById($mandateId);

        $mandate->update([
            'status' => MandateStatus::ACTIVE,
            'note' => $reason.' Received at '.date('Y-m-d H:i:s').'UTC',
            'cancelled_at' => null,
            'cancel_reason' => null,
        ]);

        $location = $mandate->locationPaymentGateway?->location ?? null;

        if (! $location) {
            Log::emergency('Could not determine location to cancel GoCardless mandate.', ['mandate_id' => $mandate->getKey()]);

            return;
        }

        $crmService = resolve(CrmService::class);
        $admins = (new TenantUserService())->getTenantUsersBy(tenant: $location->tenant, userTypes: [UserType::HEAD_COACH, UserType::BOX_ADMIN], userStatuses: [UserStatus::ACTIVE]);

        foreach ($admins as $admin) {
            $content = Markdown::parse(
                view('emails.gocardless.mandate-reinstated', [
                    'memberName' => $mandate->user->full_name,
                    'adminName' => $admin->full_name,
                    'reason' => $reason,
                ])->render()
            )->__toString();

            $crmService->createScheduledEmail(
                content: $content,
                subject: 'Octiv - Mandate has Expired',
                to: $admin->user->email,
                replyTo: $crmService->getReplyTo($location),
                tenant: $location->tenant,
                location: $location,
            );
        }
    }

    private function getMandateById(string $id, ?bool $isActive = null): MandateGoCardless
    {
        $mandateQueryBuilder = MandateGoCardless::where('mandate', $id);

        if (is_bool($isActive) && $isActive) {
            $mandateQueryBuilder->where('status', MandateStatus::ACTIVE);
        }

        if (! $mandate = $mandateQueryBuilder->first()) {
            throw new Exception($isActive ? 'An active mandate ' : 'Mandate '."with ID $id does not exist.");
        }

        return $mandate;
    }

    //////////////////////////////////////////
    // Start of payment functions
    //////////////////////////////////////////

    public function submitBatch(DebitBatch $debitBatch, ?Carbon $chargeDateOverride = null): void
    {
        if (! $this->isLocationOnboarded($debitBatch->location)) {
            throw new Exception("Facility {$debitBatch->location->name} - {$debitBatch->location->getKey()} : is not on-board with GoCardless.");
        }

        $userBatches = (new UserBatchService())->getUserBatchesForDebitBatchSubmission($debitBatch);

        $debitBatch->startLogEntry();

        if ($userBatches->isEmpty()) {
            $debitBatch->log("No user batches found for {$debitBatch->location->name}'s batch: {$debitBatch->getKey()}.")->endLogEntry();
            $debitBatch->save();
            throw new Exception('Debit batch has no user batches for submission.');
        }

        /** @var UserBatch $userBatch */
        foreach ($userBatches as $userBatch) {
            if (! ($invoice = $userBatch->invoice)) {
                continue;
            }

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

    public function requestPayment(User $user, Location $location, int $amount, string $currency, ?Carbon $chargeDate = null, ?string $uniqueKey = null, ?array $metadata = []): ?string
    {
        if ($amount <= 0) {
            throw new Exception('Amount needs to be a positive integer in lowest currency denominator (cents/pence)');
        }

        if (strlen($currency) !== 3) {
            // @todo make this smarter - check a list of valid currencies
            throw new Exception('Currency needs to be a 3 character string.');
        }

        if (! $this->isUserOnBoard($user, $location)) {
            throw new Exception('User needs to have an active mandate to be charged.');
        }

        if (count($metadata) > 3) {
            throw new Exception('Metadata can only have 3 entries.');
        }

        foreach ($metadata as $key => $value) {
            if (strlen($key) > 50) {
                throw new Exception("Metadata key '$key' can only have 50 characters, not.");
            }

            if (strlen($value) > 500) {
                throw new Exception("Metadata value '$value' can only have 500 characters.");
            }
        }

        $locationPaymentGateway = (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)->first();
        $token = $locationPaymentGateway?->settings->token;

        $mandateToken = $this->getMandateForUser(user: $user, location: $location, statuses: MandateStatus::activeStatusValues())
            ->select('go_cardless_mandates.mandate')
            ->value('mandate');

        if (! $token) {
            throw new Exception("$location->name does not have an active token");
        }

        if (! $mandateToken) {
            throw new Exception("$user->full_name does not have an active mandate");
        }

        /**
         * Quoted from GoCardless: https://developer.gocardless.com/getting-started/api/taking-your-first-payment/
         * You’ll notice here that we provide an Idempotency-Key header. If we provide a unique string specific to this payment (for example its ID in our own database),
         * the API will ensure this payment is only ever created once.
         */
        $headers = $uniqueKey !== null ? ['Idempotency-Key' => $uniqueKey] : [];

        $params = [
            'params' => [
                'amount' => $amount,
                'currency' => $currency,
                'links' => [
                    'mandate' => $mandateToken,
                ],
                'metadata' => $metadata,
            ],
            'headers' => $headers,
        ];

        if ($chargeDate instanceof Carbon) {
            $params['params']['charge_date'] = $chargeDate->format('Y-m-d');
        }

        $payment = $this->getClient($token)->payments()->create($params);

        return $payment->id;
    }

    public function requestPaymentForInvoice(UserInvoice $invoice, ?bool $useEarliestChargeDate = false): ?string
    {
        if ($invoice->isPaid()) {
            return $invoice->gateway_payment_id;
        }

        $currency = strtoupper($invoice->locationUser->location->tenant->memberCurrency->code);

        try {
            $amountInCents = $invoice->userBatch?->amount_in_cents ?? $invoice->amount_in_cents;

            $paymentId = $this->requestPayment(
                $invoice->locationUser->user,
                $invoice->locationUser->location,
                $amountInCents,
                $currency,
                $useEarliestChargeDate ? null : $invoice->due_on,
                sprintf('invoice_%s', $invoice->getKey()),
                [
                    'invoice_number' => (string) $invoice->getKey(),
                ]
            );

            $invoice->update([
                'status' => InvoiceStatus::SUBMITTED,
                'gateway_payment_id' => $paymentId,
            ]);

            return $paymentId;
        } catch (Exception $ex) {
            $timeStamp = now()->toDateTimeString();

            // Only set to unpaid if invoice does not have a payment ID from GC
            if (! $invoice->gateway_payment_id) {
                $invoice->status = InvoiceStatus::UNPAID;
            }

            $invoice->gateway_notes = "$timeStamp: Error processing payment with reason: {$ex->getMessage()}";

            $invoice->save();

            return null;
        }
    }

    public function cancelPaymentForInvoice(Location $location, UserInvoice $invoice): ?string
    {
        $locationPaymentGateway = (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)->first();
        $token = $locationPaymentGateway?->settings->token;

        $payment = $this->getPayment($token, $invoice->gateway_payment_id);

        if ($payment && $payment->status == 'pending_submission') {
            try {
                $payment = $this->getClient($token)->payments()->cancel($invoice->gateway_payment_id);

                if ($payment->status != 'cancelled') {
                    return 'Payment could not be cancelled. PaymentId: '.$payment->id;
                }
            } catch (InvalidStateException $e) {
                // The action you are trying to perform is invalid due to the state of the resource you are requesting it on.
                // For example, a payment you are trying to cancel might already have been submitted. The errors will give more details.
                Log::error($e->getTraceAsString());

                return 'Payment was not cancelled. PaymentId: '.$payment->id.' Error: '.$e->getMessage();
            }
        }

        return null;
    }

    private function handlePaymentPaidOut(string $paymentId): void
    {
        $invoice = $this->getInvoiceByPaymentId($paymentId);

        if ($invoice->status !== InvoiceStatus::SUBMITTED) {
            throw new Exception("Payment event cannot be handled. Invoice with ID {$invoice->getKey()} has an invalid status of '{$invoice->status->name}', and should be 'submitted'.");
        }

        $locationPaymentGateway = $invoice->locationPaymentGateway;

        if (! $locationPaymentGateway) {
            $location = $invoice->invoice_location;

            if (! $location) {
                throw new Exception("No location found for invoice {$invoice->getKey()}.");
            }

            $locationPaymentGateway = (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)->first();

            if (! $locationPaymentGateway) {
                throw new Exception("No location payment gateway found for invoice {$invoice->getKey()}.");
            }
        }

        if (! $locationPaymentGateway->settings) {
            throw new Exception("Appropriate settings for facility {$invoice->invoice_location->getKey()} do not exist.");
        }

        if (! $token = $locationPaymentGateway->settings->token) {
            throw new Exception("GoCardless token for facility {$invoice->invoice_location->getKey()} does not exist.");
        }

        $goCardlessPayment = $this->getClient($token)->payments()->get($paymentId);

        if (! is_object($goCardlessPayment)) {
            throw new Exception("Could not retrieve a payment object from GoCardless for payment ID $paymentId");
        }

        $paymentType = $invoice->userBatch ? InvoicePaymentType::DEBIT_ORDER : InvoicePaymentType::ADHOC;
        $tagIds = [Tag::query()->paymentTag(PaymentProcessorTag::GOCARDLESS)->first()->getKey()];

        (new InvoiceService)->createPaymentForInvoice($invoice, $paymentType, ((int) $goCardlessPayment->amount) / 100, null, $paymentId, $tagIds);
    }

    private function handlePaymentFailedOrCancelled(string $paymentId, string $reason): void
    {
        $invoice = $this->getInvoiceByPaymentId($paymentId);

        $invoice->update([
            'status' => InvoiceStatus::UNPAID,
            'gateway_notes' => "GoCardless payment failed/cancelled with reason: $reason",
        ]);

        if ($invoice->discriminator === InvoiceDiscriminator::SIGN_UP_INVOICE) {
            $locationUser = $invoice->userLocation;
            (new WidgetService())->completeSignUp($locationUser->user->getKey(), $locationUser->location->tenant->getKey(), false);
        } elseif ($invoice->discriminator === InvoiceDiscriminator::DROP_IN_INVOICE) {
            (new WidgetService())->completeDropIn($invoice, false);
        }
    }

    private function handlePaymentChargedBack(string $paymentId, string $reason): void
    {
        $invoice = $this->getInvoiceByPaymentId($paymentId);

        $payments = UserInvoicePayment::query()
            ->where('reference', '=', $paymentId)
            ->where('deleted', '=', false);

        $toDeduct = 0;

        $payments->each(function ($payment) use (&$toDeduct, $reason) {
            if (! in_array($payment->type, [InvoicePaymentType::ADHOC, InvoicePaymentType::DEBIT_ORDER])) {
                return;
            }

            $taggable = Taggable::query()
                ->where('morphable_id', '=', $payment->getKey())
                ->where('morphable_model', '=', (new UserInvoicePayment)->getTable())
                ->where('tag_id', '=', 3)
                ->first();

            if ($taggable instanceof Taggable && $taggable->tag->type === TagType::PAYMENT) {
                $payment->update([
                    'notes' => "Charged back by GoCardless: $reason",
                    'deleted' => true,
                ]);

                $toDeduct += $payment->amount;
            }
        });

        $invoice->update([
            'status' => $toDeduct >= 0 ? InvoiceStatus::UNPAID : $invoice->status,
            'gateway_notes' => "Charged back by GoCardless: $reason",
        ]);
    }

    private function getInvoiceByPaymentId(string $paymentId): UserInvoice
    {
        if (! $invoice = UserInvoice::query()->where('gateway_payment_id', $paymentId)->first()) {
            throw new Exception("Invoice with payment ID $paymentId not found.");
        }

        return $invoice;
    }

    //////////////////////////////////////////
    // Start of general functions
    //////////////////////////////////////////

    public function getCustomer(string $token, $customerId): Customer|string
    {
        try {
            $customer = $this->getClient($token)->customers()->get($customerId);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }

        return $customer;
    }

    public function listMandates(string $token, $params = null): ListResponse|string
    {
        return $this->getClient($token)->mandates()->list($params);
    }

    public function getPayouts(string $token, Carbon $startDate, Carbon $endDate, int $limit, ?string $after = null, ?string $before = null): ?array
    {
        $payouts = null;
        $payoutsData = null;

        try {
            $params = [
                'created_at[gte]' => $startDate->format('c'),
                'created_at[lte]' => $endDate->format('c'),
                'limit' => $limit,
            ];

            if ($after) {
                $params['after'] = $after;
            }

            if ($before) {
                $params['before'] = $before;
            }

            /** @var ListResponse $payoutsData */
            $payoutsData = $this->getClient($token)->payouts()->list([
                'params' => $params,
            ]);

            /** @var Payout $payout */
            foreach ($payoutsData->records as $payout) {
                $payouts[] = [
                    'id' => $payout->id,
                    'status' => $payout->status,
                    'currency' => $payout->currency,
                    'deductedFees' => bcdiv($payout->deducted_fees, 100, 2),
                    'amount' => bcdiv($payout->amount, 100, 2),
                    'arrivalDate' => $payout->arrival_date,
                    'createdAt' => $payout->created_at,
                    'reference' => $payout->reference,
                ];
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }

        return [
            'payouts' => $payouts,
            'after' => $payoutsData?->after,
            'before' => $payoutsData?->before,
        ];
    }

    public function getPayoutItems(string $token, string $payoutId, int $limit, ?string $after = null, ?string $before = null): array
    {
        $payoutItems = collect();

        $params = [
            'payout' => $payoutId,
            'limit' => $limit,
        ];

        if ($after) {
            $params['after'] = $after;
        }

        if ($before) {
            $params['before'] = $before;
        }

        /** @var ListResponse $payoutItemsResponse */
        $payoutItemsResponse = $this->getClient($token)->payoutItems()->list([
            'params' => $params,
        ]);

        /** @var PayoutItem $payoutItem */
        foreach ($payoutItemsResponse->records as $payoutItem) {
            $payoutItems->push([
                'amount' => bcdiv($payoutItem->amount, 100, 2),
                'type' => $payoutItem->type,
                'payment_id' => $payoutItem->links->payment,
                'invoice' => null,
            ]);
        }

        return [
            'payout_items' => $payoutItems,
            'after' => $payoutItemsResponse?->after,
            'before' => $payoutItemsResponse?->before,
        ];
    }

    public function getPayment(string $token, $paymentId): Payment|string
    {
        return $this->getClient($token)->payments()->get($paymentId);
    }
}
