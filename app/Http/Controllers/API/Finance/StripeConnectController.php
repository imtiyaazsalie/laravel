<?php

namespace App\Http\Controllers\API\Finance;

use App\Enums\FinancePaymentTokenType;
use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserType;
use App\Helpers\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetPaymentMethodsRequest;
use App\Http\Requests\StripeConnect\AttachPaymentMethodRequest;
use App\Http\Requests\StripeConnect\ConnectedWebhookRequest;
use App\Http\Requests\StripeConnect\CreateCheckoutSessionRequest;
use App\Http\Requests\StripeConnect\DeleteAccountRequest;
use App\Http\Requests\StripeConnect\DeleteSetupIntentRequest;
use App\Http\Requests\StripeConnect\GetAccountSessionRequest;
use App\Http\Requests\StripeConnect\GetDebitOrderMembersRequest;
use App\Http\Requests\StripeConnect\GetRefreshAccountLinkUrlRequest;
use App\Http\Requests\StripeConnect\ListAccountCapabilitiesRequest;
use App\Http\Requests\StripeConnect\ListRecurringCardPaymentsRequest;
use App\Http\Requests\StripeConnect\PostAccountLinkUrlRequest;
use App\Http\Requests\StripeConnect\PostLocationSetupIntent;
use App\Http\Requests\StripeConnect\PostSetupIntentLinkRequest;
use App\Http\Requests\StripeConnect\SendSetupIntentLinkRequest;
use App\Http\Requests\StripeConnect\SetupIntentRequest;
use App\Http\Requests\StripeConnect\UpdateAccountCapabilitiesRequest;
use App\Http\Requests\StripeConnect\WebhookRequest;
use App\Http\Resources\StripeConnect\PaymentMethods\PaymentMethodResource;
use App\Http\Resources\TenantUserResource;
use App\Http\Resources\UserMinimalResource;
use App\Http\Resources\UserTenantResource;
use App\Models\FinancePaymentToken;
use App\Models\Location;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserInvoice;
use App\Services\PaymentGateways\StripeConnectService;
use App\Services\PaymentGateways\StripeConnectWebhookService;
use App\Services\PaymentTokenService;
use App\Services\TenantUserService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StripeConnectController extends Controller
{
    public function __construct(private readonly StripeConnectService $stripeConnectService)
    {
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('page', 'boolean', required: false)]
    #[QueryParam('per_page', 'integer', required: false)]
    public function listDebitOrderMembers(GetDebitOrderMembersRequest $request)
    {
        $tenantUsers = QueryBuilder::for(TenantUser::class)
            ->select('user_to_box.*')
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'user_to_box.box_id'),
                AllowedFilter::callback('location_id', function (Builder $query, $value) {
                    $query->join('user_to_facility', 'user_to_box.user_id', 'user_to_facility.user_id')
                        ->where('user_to_facility.box_facility_id', '=', $value)
                        ->whereDate('user_to_facility.end_date', '>', now());
                }),
            ])
            ->join('users', 'users.user_id', '=', 'user_to_box.user_id')
            ->where('user_to_box.user_type_id', '=', UserType::GYM_MEMBER)
            ->where('user_to_box.user_debit_status_id', '=', UserDebitStatus::DEBIT_ORDER)
            ->active()
            ->orderBy('users.name', 'ASC')
            ->orderBy('users.surname', 'ASC')
            ->_paginate();

        /** @var TenantUser $tenantUser */
        foreach ($tenantUsers as $tenantUser) {
            $location = (new TenantUserService())->getLocationUserByTenant($tenantUser->user, $tenantUser->tenant)?->location;

            if (! $location) {
                continue;
            }

            $paymentToken = (new PaymentTokenService())->getFinancePaymentToken($this->stripeConnectService->getDebitOrderPaymentMethodsForTenant($tenantUser->tenant), FinancePaymentTokenType::USER, $tenantUser->user, $location);

            $tenantUser->setAttribute('is_onboarded', $paymentToken instanceof FinancePaymentToken);

            if ($paymentToken instanceof FinancePaymentToken && $paymentToken->setup_intent_id) {
                $setupIntent = $this->stripeConnectService->getSetupIntent($location, $paymentToken->setup_intent_id, ['expand' => ['mandate']]);

                if ($setupIntent->mandate) {
                    $tenantUser->setAttribute('stripe_mandate', $setupIntent->mandate);
                }
            }
        }

        return UserTenantResource::collection($tenantUsers);
    }

    public function postAccountSession(Location $location, GetAccountSessionRequest $request)
    {
        return $this->stripeConnectService->createAccountSession($location);
    }

    public function postAccountLinkUrl(Location $location, PostAccountLinkUrlRequest $request)
    {
        return response()->json($this->stripeConnectService->createAccountLinkUrl($location));
    }

    public function getRefreshAccountLinkUrl(Location $location, GetRefreshAccountLinkUrlRequest $request)
    {
        return redirect($this->stripeConnectService->createAccountLinkUrl($location));
    }

    public function getAccountCapabilities(Location $location, ListAccountCapabilitiesRequest $request)
    {
        return $this->stripeConnectService->getAccountCapabilities($location);
    }

    public function putAccountCapabilities(Location $location, UpdateAccountCapabilitiesRequest $request)
    {
        $this->stripeConnectService->updateAccountCapabilities($location, $request);

        return response()->noContent();
    }

    public function deleteAccount(Location $location, DeleteAccountRequest $request)
    {
        $this->stripeConnectService->deleteAccount($location, $request);

        return response()->noContent();
    }

    public function postDebitOrderSetupIntent(PostSetupIntentLinkRequest $request)
    {
        $location = Location::findOrFail($request->location_id);
        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user_id, $location->tenant_id);

        if (! $tenantUser) {
            abort(404, 'Tenant membership could not be found.');
        }

        if ($tenantUser->user_debit_status_id !== UserDebitStatus::DEBIT_ORDER) {
            abort(400, 'User is not a debit order/direct debit user');
        }

        if (! $this->stripeConnectService->isLocationOnboarded($location)) {
            abort(400, "This user's location($location->name) has not yet been on-boarded to Stripe yet.");
        }

        $tenantPaymentMethods = $this->stripeConnectService->getDebitOrderPaymentMethodsForTenant($tenantUser->tenant);
        $paymentToken = (new PaymentTokenService())->getFinancePaymentToken($tenantPaymentMethods, FinancePaymentTokenType::USER, $tenantUser->user, $location);

        if ($paymentToken) {
            abort(400, 'User has already has a payment token in our system');
        }

        return response()->json($this->stripeConnectService->setupIntent($tenantUser->user, $tenantPaymentMethods, $location));
    }

    public function sendSetupIntentLink(SendSetupIntentLinkRequest $request)
    {
        $tenant = Tenant::findOrFail($request->tenant_id);
        $userIds = $request->user_ids;

        foreach ($userIds as $userId) {
            $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenant);

            if (! $tenantUser || $tenantUser->tenant_id !== $tenant->getKey() || $tenantUser->user_debit_status_id !== UserDebitStatus::DEBIT_ORDER) {
                continue;
            }

            $user = $tenantUser->user;
            $locationUser = (new TenantUserService())->getLocationUserByTenant($user, $tenant);

            if (! $locationUser) {
                continue;
            }

            $location = $locationUser->location;

            if (! $this->stripeConnectService->isLocationOnboarded($location)) {
                continue;
            }

            if ((new PaymentTokenService())->getFinancePaymentToken($this->stripeConnectService->getDebitOrderPaymentMethodsForTenant($tenant), FinancePaymentTokenType::USER, $user, $location)) {
                continue;
            }

            $this->stripeConnectService->sendSetupIntentMail($tenantUser);
        }

        return response()->noContent();
    }

    public function webhook(WebhookRequest $request)
    {
        try {
            (new StripeConnectWebhookService())->handleWebhook($request);
        } catch (Exception $ex) {
            return new Response($ex->getMessage());
        }

        return response([], 200);
    }

    public function connectedWebhook(ConnectedWebhookRequest $request)
    {
        try {
            (new StripeConnectWebhookService())->handleConnectedWebhook($request);
        } catch (Exception $ex) {
            return new Response($ex->getMessage());
        }

        return response([], 200);
    }

    public function setupIntent(SetupIntentRequest $request)
    {
        $user = User::find($request->input('user_id'));
        $paymentMethods = $request->input('payment_methods');
        $location = Location::find($request->input('location_id'));

        return response()->json([
            'user' => new UserMinimalResource($user),
            'payment_methods' => $paymentMethods,
            'url' => $this->stripeConnectService->setupIntent($user, $paymentMethods, $location),
        ]);
    }

    public function attachPaymentMethod(AttachPaymentMethodRequest $request): ?string
    {
        $userId = $request->input('user_id');
        $locationId = $request->input('location_id');
        $token = $request->input('token');

        $user = User::find($userId);
        $location = Location::find($locationId);

        return $this->stripeConnectService->attachPaymentMethod($token, $user, $location);
    }

    public function paymentMethods(GetPaymentMethodsRequest $request)
    {
        // Fetch payment methods and transform them into a resource collection
        $paymentMethods = $this->stripeConnectService->getPaymentMethods($request);

        return PaymentMethodResource::collection($paymentMethods);
    }

    public function listUserPlatformCards(User $user)
    {
        // Fetch platform cards and return as a resource collection
        return PaymentMethodResource::collection($this->stripeConnectService->getUserPlatformCards($user));
    }

    public function deleteSetupIntent(DeleteSetupIntentRequest $request, FinancePaymentToken $financePaymentToken)
    {
        $financePaymentToken->delete();

        return response()->noContent();
    }

    public function recurringCardPayments(ListRecurringCardPaymentsRequest $request)
    {
        // Retrieve tenant users with necessary joins and filters
        $tenantUsers = $this->getFilteredTenantUsers();

        foreach ($tenantUsers as $tenantUser) {
            $user = $tenantUser->user;
            $location = $this->getUserLocation($user, $tenantUser->tenant);

            if ($location) {
                $paymentMethods = $this->fetchPaymentMethods($user, $location);

                // Update user attributes
                $tenantUser->setAttribute('payment_method', $paymentMethods->first());
                $tenantUser->setAttribute('is_onboarded', $paymentMethods->isNotEmpty());
                //$user->load('userTenant');
            }
        }

        return TenantUserResource::collection(
            CollectionHelper::paginate($tenantUsers, request()->input('per_page'))
        );
    }

    private function getFilteredTenantUsers()
    {
        return QueryBuilder::for(TenantUser::class)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'user_to_box.box_id'),
                AllowedFilter::callback('location_id', function (Builder $query, $value) {
                    $query->join('user_to_facility', 'user_to_box.user_id', 'user_to_facility.user_id')
                        ->where('user_to_facility.box_facility_id', $value)
                        ->whereDate('user_to_facility.end_date', '>', now());
                }),
            ])
            ->where('user_to_box.user_type_id', UserType::GYM_MEMBER)
            ->where('user_to_box.user_debit_status_id', UserDebitStatus::TOKENIZED_CARD)
            ->active()
            ->join('users', 'users.user_id', 'user_to_box.user_id')
            ->orderBy('users.name')
            ->orderBy('users.surname')
            ->get();
    }

    private function getUserLocation($user, $tenant)
    {
        return (new TenantUserService())->getLocationUserByTenant($user, $tenant)?->location;
    }

    private function fetchPaymentMethods($user, $location)
    {
        $data = request();
        $data->query->add([
            'filter' => [
                'user_id' => $user->user_id,
                'location_id' => $location->getKey(),
                'type' => 'card',
                'default' => 1,
            ],
        ]);

        return (new StripeConnectService())->getPaymentMethods($data);
    }

    public function createCheckoutSession(CreateCheckoutSessionRequest $request)
    {
        $invoice = UserInvoice::find($request->input('invoice_id'));
        $locationPaymentGatewaySettings = LocationPaymentGatewaySettings::with('locationPaymentGateway')
            ->whereRelation('locationPaymentGateway', 'payment_gateway_id', '=', PaymentGateway::STRIPE_CONNECT)
            ->findOrFail($request->input('settings_id'));

        if (! $locationPaymentGatewaySettings->connected_account_id) {
            abort(404, 'Location has not onboarded to Stripe Connect yet.');
        }

        return response()->json([
            'url' => $this->stripeConnectService->getOrCreateCheckOutSession($invoice, $locationPaymentGatewaySettings)?->url,
        ]);
    }

    public function createLocationSetupIntent(PostLocationSetupIntent $request)
    {
        return response()->json($this->stripeConnectService->createLocationSetupIntent(Location::find($request->input('location_id'))));
    }
}
