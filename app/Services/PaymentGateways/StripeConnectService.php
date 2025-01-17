<?php

namespace App\Services\PaymentGateways;

use App\Enums\FinancePaymentTokenType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Enums\StripeConnect\StripeConnectPaymentMethodType;
use App\Models\FinancePaymentToken;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Services\CrmService;
use App\Services\LocationService;
use App\Services\PaymentTokenService;
use App\Services\TenantUserService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Stripe\Account;
use Stripe\AccountSession;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\StripeClient;

class StripeConnectService
{
    private StripeClient $stripeClient;

    public function __construct()
    {
        $this->stripeClient = new StripeClient(config('stripe-connect.secretKey'));
    }

    //////////////////////////////////////////
    // Start of merchant service functions
    //////////////////////////////////////////

    public function getDebitOrderPaymentMethodsForTenant(Tenant $tenant): array
    {
        $europeanCountryCodes = [
            'AU', // Australia
            'AT', // Austria
            'BE', // Belgium
            'BG', // Bulgaria
            'CA', // Canada
            'HR', // Croatia
            'CY', // Cyprus
            'CZ', // Czech Republic
            'DK', // Denmark
            'EE', // Estonia
            'FI', // Finland
            'FR', // France
            'DE', // Germany
            'GI', // Gibraltar
            'GR', // Greece
            'HK', // Hong Kong
            'HU', // Hungary
            'IE', // Ireland
            'IT', // Italy
            'JP', // Japan
            'LV', // Latvia
            'LI', // Liechtenstein
            'LT', // Lithuania
            'LU', // Luxembourg
            'MT', // Malta
            'MX', // Mexico
            'NL', // Netherlands
            'NZ', // New Zealand
            'NO', // Norway
            'PL', // Poland
            'PT', // Portugal
            'RO', // Romania
            'SG', // Singapore
            'SK', // Slovakia
            'SI', // Slovenia
            'ES', // Spain
            'SE', // Sweden
            'CH', // Switzerland
        ];

        $countryCode = $tenant->region->country_code_iso2;

        $paymentMethods = match (true) {
            $countryCode === 'CA' => [PaymentMethod::TYPE_ACSS_DEBIT], // Canada
            $countryCode === 'AU' => [PaymentMethod::TYPE_AU_BECS_DEBIT], // Australia
            $countryCode === 'GB' => [PaymentMethod::TYPE_BACS_DEBIT], // United Kingdom
            $countryCode === 'BE' => [PaymentMethod::TYPE_BANCONTACT, PaymentMethod::TYPE_SEPA_DEBIT], // Belgium
            $countryCode === 'NL' => [PaymentMethod::TYPE_IDEAL, PaymentMethod::TYPE_SEPA_DEBIT], // Netherlands
            $countryCode === 'US' => [PaymentMethod::TYPE_US_BANK_ACCOUNT], // United States
            in_array($countryCode, $europeanCountryCodes) => [PaymentMethod::TYPE_SEPA_DEBIT], // Eu region countries
            default => null,
        };

        return $paymentMethods ?? abort(400, 'This region cannot take user debit-orders/direct debits');
    }

    public function isLocationOnboarded(Location $location): bool
    {
        return (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::DEBIT_ORDER, PaymentGateway::STRIPE_CONNECT)->whereHas('settings', function ($query) {
            $query->whereNotNull('connected_account_id');
        })->exists();
    }

    public function getLocationOnboardingStatus(Location $location): string
    {
        $account = $this->getAccount($location);

        if (! $account) {
            return 'notOnboarded';
        }

        $requirements = $account->requirements;

        if (isset($requirements['disabled_reason']) && is_array($requirements['disabled_reason']) && in_array('rejected', $requirements['disabled_reason'])) {
            return 'rejected';
        }

        if ($account->payouts_enabled && $account->charges_enabled) {
            if ($requirements['pending_verification']) {
                return 'pendingEnablement';
            }

            if (! $requirements['disabled_reason'] && ! $requirements['currently_due']) {
                if (! $requirements['eventually_due']) {
                    return 'onboardingComplete';
                } else {
                    return 'enabled';
                }
            } else {
                return 'restricted';
            }
        }

        if (! $account->payouts_enabled && $account->charges_enabled) {
            return 'restrictedPayoutsDisabled';
        }

        if (! $account->charges_enabled && $account->payouts_enabled) {
            return 'restrictedChargesDisabled';
        }

        if ($requirements['past_due']) {
            return 'restrictedPastDue';
        }

        if ($requirements['pending_verification']) {
            return 'pendingDisabled';
        }

        return 'restricted';
    }

    public function getAccount(Location $location): ?Account
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($location);

        if (! $locationPaymentGateway || ! $locationPaymentGateway->settings?->connected_account_id) {
            return null;
        }

        return $this->stripeClient->accounts->retrieve($locationPaymentGateway->settings->connected_account_id);
    }

    private function getLocationPaymentGateway(Location $location, ?bool $isActive = null)
    {
        return LocationPaymentGateway::query()
            ->where('box_facility_id', $location->getKey())
            ->where('payment_gateway_id', '=', PaymentGateway::STRIPE_CONNECT)
            ->where('context', '=', PaymentGatewayContext::DEBIT_ORDER)
            ->whereHas('settings')
            ->when(isset($isActive), function ($query) use ($isActive) {
                $query->where('is_active', '=', $isActive);
            })
            ->orderBy('facility_to_payment_gateway_id', 'desc')
            ->first();
    }

    public function createAccountLinkUrl(Location $location): string
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($location);

        if (! $locationPaymentGateway) {
            $locationPaymentGateway = LocationPaymentGateway::create([
                'location_id' => $location->getKey(),
                'context' => PaymentGatewayContext::DEBIT_ORDER,
                'payment_gateway_id' => PaymentGateway::STRIPE_CONNECT,
            ]);

            LocationPaymentGatewaySettings::create([
                'location_payment_gateway_id' => $locationPaymentGateway->getKey(),
                'public_token' => sha1(uniqid(true)),
                'for_sign_up' => true,
                'discr' => PaymentGateway::getLocationPaymentGatewayDiscr(PaymentGateway::STRIPE_CONNECT),
            ]);
        }

        // Create account if one is not linked yet
        if (! $locationPaymentGateway->settings->connected_account_id) {
            $locationPaymentGateway->settings()->update(['connected_account_id' => $this->createAccount($locationPaymentGateway)->id]);
        }

        if (! $locationPaymentGateway->isActive()) {
            $locationPaymentGateway->settings()->update(['is_active' => true]);
        }

        $locationPaymentGateway->refresh();

        return $this->stripeClient->accountLinks->create([
            'account' => $locationPaymentGateway->settings->connected_account_id,
            'refresh_url' => config('app.url')."/finances/stripe-connect/{$location->getKey()}/refresh-account-link-url",
            'return_url' => config('octiv.web_app_url').'/settings/payment-gateways',
            'type' => 'account_onboarding',
            'collection_options' => [
                'fields' => 'eventually_due',
                'future_requirements' => 'include',
            ],
        ])->url;
    }

    private function createAccount(LocationPaymentGateway $locationPaymentGateway): ?Account
    {
        $location = $locationPaymentGateway->location;

        try {
            return $this->stripeClient->accounts->create([
                'controller' => [
                    'stripe_dashboard' => ['type' => 'none'],
                ],
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'email' => auth()->user()->email,
                'business_profile' => [
                    'name' => $location->tenant->name.'-'.$location->prefix,
                    'product_description' => $location->tenant->description,
                    'url' => $location->tenant->website_url,
                ],
                'country' => $location->tenant->region->country_code_iso2,
                'metadata' => [
                    'tenant_id' => $location->tenant_id,
                    'tenant_name' => $location->tenant->name,
                    'location_id' => $location->getKey(),
                    'location_name' => $location->name,
                    'location_payment_gateway_id' => $locationPaymentGateway->getKey(),
                ],
            ]);
        } catch (ApiErrorException $e) {
            abort(400, $e->getMessage());
        }
    }

    public function createAccountSession(Location $location): ?AccountSession
    {
        try {
            $locationPaymentGateway = $this->getLocationPaymentGateway($location);

            if (! $locationPaymentGateway || ! $locationPaymentGateway->settings?->connected_account_id) {
                return null;
            }

            return $this->stripeClient->accountSessions->create([
                'account' => $locationPaymentGateway->settings->connected_account_id,
                'components' => [
                    'account_management' => ['enabled' => true],
                    'documents' => ['enabled' => true],
                    'notification_banner' => ['enabled' => true],
                    'payments' => ['enabled' => true],
                    'payouts' => ['enabled' => true],
                    'payouts_list' => ['enabled' => true],
                ],
            ]);
        } catch (ApiErrorException $e) {
            abort(400, $e->getMessage());
        }
    }

    public function getAccountCapabilities(Location $location): ?array
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($location);

        if (! $locationPaymentGateway || ! $locationPaymentGateway->settings?->connected_account_id) {
            return null;
        }

        try {
            return $this->stripeClient->accounts->allCapabilities($locationPaymentGateway->settings->connected_account_id)->data;
        } catch (ApiErrorException $e) {
            abort(400, $e->getMessage());
        }
    }

    public function updateAccountCapabilities(Location $location, Request $request)
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($location);

        if (! $locationPaymentGateway || ! $locationPaymentGateway->settings?->connected_account_id) {
            return null;
        }

        $currentRequestedAccountCapabilities = array_column(array_filter($this->getAccountCapabilities($location), fn ($accountCapability) => $accountCapability->requested === true), 'id'); //array_column($this->getAccountCapabilities($location), 'id');
        $selectedCapabilities = $request->account_capability_ids;

        $capabilitiesToBeEnabled = array_diff($selectedCapabilities, $currentRequestedAccountCapabilities);
        $capabilitiesToBeDisabled = array_diff($currentRequestedAccountCapabilities, $selectedCapabilities);

        try {
            // Enable capabilities that have been added
            foreach ($capabilitiesToBeEnabled as $capabilityId) {
                $this->stripeClient->accounts->updateCapability($locationPaymentGateway->settings->connected_account_id, $capabilityId, ['requested' => true]);
            }

            // Disable capabilities that have been removed
            foreach ($capabilitiesToBeDisabled as $capabilityId) {
                $this->stripeClient->accounts->updateCapability($locationPaymentGateway->settings->connected_account_id, $capabilityId, ['requested' => false]);
            }
        } catch (ApiErrorException $e) {
            abort(400, $e->getMessage());
        }
    }

    public function deleteAccount(Location $location, Request $request)
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($location);

        if (! $locationPaymentGateway || ! $locationPaymentGateway->settings?->connected_account_id) {
            return null;
        }

        try {
            // This is commented out because we not sure if we want to permanently delete the connected account so we're just deactivating the location payment gateway for now
            // $this->stripeClient->accounts->delete($locationPaymentGateway->settings->connected_account_id);

            $locationPaymentGateway->update(['is_active' => false]);
        } catch (ApiErrorException $e) {
            abort(400, $e->getMessage());
        }
    }

    public function setupIntent(User $user, array $paymentMethods, ?Location $location = null): ?string
    {
        $checkoutData = [
            'payment_method_types' => [$paymentMethods],
            'success_url' => config('octiv.web_app_url').'?sessionId={CHECKOUT_SESSION_ID}',
            'mode' => 'setup',
            'client_reference_id' => $user->getKey(),
        ];

        return match (true) {
            in_array(PaymentMethod::TYPE_CARD, $paymentMethods) && ! $location => $this->cardSetupIntent($checkoutData, $user),
            default => $this->setupIntentConnectAccount($location, $user, $paymentMethods),
        };
    }

    private function cardSetupIntent(array $setupIntent, User $user): ?string
    {
        $setupIntent = array_merge($setupIntent, [
            'customer_email' => $user->email,
            'setup_intent_data' => [
                'metadata' => [
                    'user_id' => $user->getKey(),
                ],
            ],
        ]);

        try {
            return $this->stripeClient->checkout->sessions->create($setupIntent)->url;
        } catch (ApiErrorException $e) {
            return $e->getMessage();
        }
    }

    public function attachPaymentMethod(string $token, User $user, Location $location): string
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($location);

        if (! $locationPaymentGateway || ! $locationPaymentGateway->settings?->connected_account_id) {
            throw new Exception('Location payment gateway not found');
        }

        $account = $locationPaymentGateway->settings?->connected_account_id;

        $customer = FinancePaymentToken::query()
            ->where('user_id', $user->getKey())
            ->where('location_id', $location->getKey())
            ->where('type', FinancePaymentTokenType::USER)
            ->first();

        try {
            // Create and attach the payment method
            $clonedPaymentMethod = $this->stripeClient->paymentMethods->create(
                ['payment_method' => $token],
                ['stripe_account' => $account]
            );

            $this->stripeClient->paymentMethods->attach(
                $clonedPaymentMethod->id,
                ['customer' => $customer->customer_id],
                ['stripe_account' => $account]
            );

            // Create and return the new FinancePaymentTokens record
            FinancePaymentToken::query()->create([
                'token' => $clonedPaymentMethod->id,
                'user_id' => $user->getKey(),
                'location_id' => $location->getKey(),
                'customer_id' => $customer->customer_id,
                'payment_gateway_id' => PaymentGateway::STRIPE_CONNECT->value,
                'payment_method' => StripeConnectPaymentMethodType::CARD->value,
            ]);

            return $clonedPaymentMethod->id; // Assuming you want to return the token ID

        } catch (ApiErrorException $e) {
            return $e->getMessage();
        }
    }

    private function setupIntentConnectAccount(Location $location, User $user, array $paymentMethods): ?string
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($location);

        if (! $locationPaymentGateway || ! $locationPaymentGateway->settings?->connected_account_id) {
            throw new Exception('Location payment gateway not found');
        }

        $accountId = $locationPaymentGateway->settings?->connected_account_id;

        $customer = FinancePaymentToken::query()
            ->where('location_id', $location->getKey())
            ->where('user_id', $user->getKey())
            ->whereNotNull('customer_id')
            ->where('type', FinancePaymentTokenType::USER)
            ->first();

        if (! $customer) {
            $customer = $this->createCustomer(
                params: [
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'metadata' => [
                        'user_id' => $user->getKey(),
                        'location_id' => $location->getKey(),
                    ],
                ],
                options: ['stripe_account' => $accountId]
            );
        }

        $checkoutData = [
            'payment_method_types' => [$paymentMethods],
            'success_url' => config('octiv.web_app_url').'?sessionId={CHECKOUT_SESSION_ID}',
            'mode' => 'setup',
            'client_reference_id' => $user->getKey(),
            'customer' => $customer->customer_id ?? $customer->id,
            'setup_intent_data' => [
                'metadata' => [
                    'user_id' => $user->getKey(),
                    'tenant_id' => $location->tenant_id,
                    'location_id' => $location->getKey(),
                ],
            ],
        ];

        try {
            return $this->stripeClient->checkout->sessions->create($checkoutData, [
                'stripe_account' => $accountId,
            ])->url;
        } catch (ApiErrorException $e) {
            return $e->getMessage();
        }
    }

    public function getPaymentMethods(Request $request): LengthAwarePaginator
    {

        $location = Location::query()->findOrFail($request->input('filter.location_id'));

        // Retrieve the account ID for the given location
        $locationPaymentGateway = $this->getLocationPaymentGateway($location);

        if (! $locationPaymentGateway || ! $locationPaymentGateway->settings?->connected_account_id) {
            throw new Exception('Location payment gateway not found');
        }
        $accountId = $locationPaymentGateway->settings?->connected_account_id;

        // Query payment methods for the specified user and location

        $paymentMethods = QueryBuilder::for(FinancePaymentToken::class)
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('location_id'),
                AllowedFilter::exact('type', 'payment_method'),
                AllowedFilter::exact('default'),
            ])
            ->where('type', FinancePaymentTokenType::USER)
            ->_paginate();

        // Enrich each payment method with additional metadata from Stripe
        $paymentMethods->getCollection()->transform(function ($paymentMethod) use ($accountId) {
            // Retrieve payment method details from Stripe
            $stripePaymentMethod = $this->stripeClient->paymentMethods
                ->retrieve($paymentMethod->token, [], ['stripe_account' => $accountId]);

            // Attach the retrieved payment method details to the current payment method
            $paymentMethod->meta = $stripePaymentMethod->{$paymentMethod->payment_method};

            return $paymentMethod;
        });

        return $paymentMethods;
    }

    public function getUserPlatformCards(User $user): LengthAwarePaginator
    {
        // Retrieve card payment methods for the specified user with null customer and location IDs
        $cardPaymentMethods = FinancePaymentToken::query()
            ->where('user_id', $user->getKey())
            ->whereNull('customer_id')
            ->whereNull('location_id')
            ->where('type', '=', FinancePaymentTokenType::USER)
            ->_paginate();

        // Enrich each card payment method with additional metadata from Stripe
        $cardPaymentMethods->getCollection()->transform(function ($cardPaymentMethod) {
            // Retrieve payment method details from Stripe
            $stripePaymentMethod = $this->stripeClient->paymentMethods
                ->retrieve($cardPaymentMethod->token);

            // Attach the retrieved payment method details to the current payment method
            $cardPaymentMethod->meta = $stripePaymentMethod->{$cardPaymentMethod->payment_method};

            return $cardPaymentMethod;
        });

        return $cardPaymentMethods;
    }

    public function sendSetupIntentMail(TenantUser $tenantUser): void
    {
        $user = $tenantUser->user;
        $tenant = $tenantUser->tenant;
        $locationUser = (new TenantUserService())->getLocationUserByTenant($user, $tenant);
        $location = $locationUser?->location;

        $crmService = resolve(CrmService::class);

        try {
            $content = Markdown::parse(
                view('emails.stripe-connect.setup-intent-link', [
                    'memberName' => $user->full_name,
                    'link' => str(config('octiv.web_app_url'))
                        ->append('/payment/stripe-mandate/', $tenantUser->user_id)
                        ->append('/', $location?->getKey()),
                ])->render()
            )->__toString();

            $crmService->createScheduledEmail(
                content: $content,
                subject: 'Octiv - Mandate Activation',
                to: $user->email,
                replyTo: $crmService->getReplyTo($location ?? $tenant),
                tenant: $tenant,
                location: $location,
                queue: 'high'
            );

            $tenantUser->update([
                'payment_token_link_sent_on' => now(),
            ]);
        } catch (\ReflectionException $e) {
            Log::error($e->getMessage());
        }
    }

    public function getSetupIntent(Location $location, string $setupIntentId, ?array $params = null)
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($location);

        if (! $locationPaymentGateway || ! $locationPaymentGateway->settings?->connected_account_id) {
            return null;
        }

        try {
            return $this->stripeClient->setupIntents->retrieve(
                id: $setupIntentId,
                params: $params,
                opts: ['stripe_account' => $locationPaymentGateway->settings->connected_account_id]
            );
        } catch (ApiErrorException $e) {
            abort(400, $e->getMessage());
        }
    }

    /**
     * @throws ApiErrorException
     */
    public function createPaymentIntent(array $params, array $ops): ?PaymentIntent
    {
        return $this->stripeClient->paymentIntents->create($params, $ops);
    }

    public function getOrCreateCheckOutSession(UserInvoice $invoice, LocationPaymentGatewaySettings $locationPaymentGatewaySettings): ?Session
    {
        $session = null;

        if ($invoice->gateway_payment_id) {
            $session = $this->stripeClient->checkout->sessions->retrieve(
                id: $invoice->gateway_payment_id,
                opts: [
                    'stripe_account' => $locationPaymentGatewaySettings->connected_account_id,
                ]
            );
        }

        // If no session found for invoice or amount has changed
        if (! $session || ($session->amount_total !== $invoice->amount_in_cents)) {
            $invoiceLineItems = $invoice->invoiceItems()->get()->map(fn (UserInvoiceItem $invoiceItem) => [
                'price_data' => [
                    'currency' => $invoice->currency,
                    'product_data' => [
                        'name' => $invoiceItem->description,
                    ],
                    'unit_amount' => $invoiceItem->amount_in_cents,
                ],
                'quantity' => $invoiceItem->quantity,
            ])->toArray();

            $webAppUrl = config('octiv.web_app_url').'/payment/'.$invoice->getKey();
            $metadata = ['invoice_id' => $invoice->getKey()];

            $params = [
                'customer_email' => $invoice->invoice_email,
                'line_items' => $invoiceLineItems,
                'mode' => 'payment',
                'success_url' => $webAppUrl.'?success=true',
                'cancel_url' => $webAppUrl.'?gid='.$locationPaymentGatewaySettings->location_payment_gateway_id,
                'client_reference_id' => $invoice->getKey(),
                'metadata' => $metadata,
                'payment_intent_data' => [
                    'metadata' => $metadata,
                ],
            ];

            $session = $this->createCheckoutSession($params, [
                'stripe_account' => $locationPaymentGatewaySettings->connected_account_id,
                'idempotency_key' => $invoice->getKey().'_'.$invoice->amount_in_cents,
            ]);

            $invoice->update([
                'gateway_payment_id' => $session->id,
            ]);
        }

        return $session;
    }

    private function createCheckoutSession(array $params, ?array $options = []): ?Session
    {
        try {
            return $this->stripeClient->checkout->sessions->create(params: $params, opts: $options);
        } catch (ApiErrorException $e) {
            abort(400, $e->getMessage());
        }
    }

    public function createLocationSetupIntent(Location $location): ?string
    {
        $user = auth()?->user();

        if (! $user) {
            return null;
        }

        $financePaymentToken = (new PaymentTokenService())->getFinancePaymentToken(
            paymentMethods: ['card'],
            financePaymentTokenType: FinancePaymentTokenType::LOCATION,
            location: $location,
            paymentGateway: PaymentGateway::STRIPE_CONNECT
        );

        $customerId = $financePaymentToken instanceof FinancePaymentToken ? $financePaymentToken->customer_id : null;

        if (! $customerId) {
            $customerId = $this->createCustomer([
                'name' => $location->tenant->name.' - '.$location->name,
                'email' => $user->email,
                'metadata' => [
                    'tenant_id' => $location->tenant_id,
                    'location_id' => $location->getKey(),
                ],
            ])->id;
        }

        return $this->createCheckoutSession([
            'payment_method_types' => ['card'],
            'success_url' => config('octiv.web_app_url').'/settings/payment-gateways?sessionId={CHECKOUT_SESSION_ID}',
            'mode' => 'setup',
            'client_reference_id' => $location->getKey(),
            'customer' => $customerId,
            'setup_intent_data' => [
                'metadata' => [
                    'tenant_id' => $location->tenant_id,
                    'location_id' => $location->getKey(),
                ],
            ],
        ])->url;
    }

    private function createCustomer(array $params, ?array $options = []): ?Customer
    {
        try {
            return $this->stripeClient->customers->create(params: $params, opts: $options);
        } catch (ApiErrorException $e) {
            abort(400, $e->getMessage());
        }
    }

    public function calculateApplicationFee(string $paymentMethod, $amount): int
    {
        // returns the amount in cents
        return match (StripeConnectPaymentMethodType::tryFrom($paymentMethod)) {
            StripeConnectPaymentMethodType::AU_BECS_DEBIT,
            StripeConnectPaymentMethodType::BACS_DEBIT,
            StripeConnectPaymentMethodType::CARD,
            StripeConnectPaymentMethodType::CARD_PRESENT => (int) bcmul(bcmul($amount, 0.005, 2), 100),
            StripeConnectPaymentMethodType::SEPA_DEBIT => 25,
            StripeConnectPaymentMethodType::IDEAL => 21,
            StripeConnectPaymentMethodType::PAYPAL => (int) bcmul(bcmul($amount, 0.003, 2), 100),
            default => 0,
        };
    }
}
