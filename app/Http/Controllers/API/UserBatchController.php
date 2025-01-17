<?php

namespace App\Http\Controllers\API;

use App\Enums\FinancePaymentTokenType;
use App\Enums\MandateType;
use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserBatch\CreateUserBatchRequest;
use App\Http\Requests\UserBatch\ListUserBatchesRequest;
use App\Http\Resources\UserBatchResource;
use App\Models\DebitBatch;
use App\Models\FinancePaymentToken;
use App\Models\LocationUser;
use App\Models\Mandate;
use App\Models\TenantUser;
use App\Models\UserBatch;
use App\Models\UserInvoice;
use App\Services\DebitBatchService;
use App\Services\FinanceService;
use App\Services\MandateService;
use App\Services\PaymentGateways\GoCardlessService;
use App\Services\PaymentGateways\StripeConnectService;
use App\Services\PaymentTokenService;
use App\Services\TenantUserService;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class UserBatchController extends Controller
{
    public function __construct(private readonly DebitBatchService $debitBatchService)
    {
    }

    #[QueryParam('filter[debit_batch_id]', 'integer', required: true)]
    #[QueryParam('filter[search]', 'string', required: false)]
    #[QueryParam('filter[is_active]', 'bool', required: false)]
    #[QueryParam('page', 'integer', required: false)]
    #[QueryParam('per_page', 'integer', required: false)]
    public function list(ListUserBatchesRequest $request)
    {
        $debitBatch = DebitBatch::findOrFail($request->input('filter.debit_batch_id'));
        $location = $debitBatch->location;

        $userBatches = QueryBuilder::for(UserBatch::class)
            ->join('users', 'user_to_batch.user_id', 'users.user_id')
            ->allowedFilters([
                AllowedFilter::exact('debit_batch_id'),
                AllowedFilter::scope('search', 'user.search'),
                AllowedFilter::exact('is_active'),
            ])
            ->with(['user', 'debitBatch'])
            ->where('users.deleted', '=', 0)
            ->allowedIncludes([
                'invoice',
            ])
            ->allowedSorts([
                AllowedSort::field('name', 'users.name'),
                AllowedSort::field('surname', 'users.surname'),
            ])
            ->_paginate();

        if (in_array($location->payment_gateway_id, [PaymentGateway::GO_CARDLESS->value, PaymentGateway::SEPA->value, PaymentGateway::STRIPE_CONNECT->value])) {
            foreach ($userBatches as $userBatch) {
                if ($location->payment_gateway_id === PaymentGateway::GO_CARDLESS->value) {
                    $mandate = (new GoCardlessService())->getMandateForUser($userBatch->user, $location)->first();

                    if ($mandate) {
                        $userBatch->user->setAttribute('gocardless_mandate', $mandate);
                    }
                }

                if ($location->payment_gateway_id === PaymentGateway::SEPA->value) {
                    $mandate = (new MandateService())->getLatestMandate($userBatch->user, $location->tenant, MandateType::SEPA);

                    if ($mandate instanceof Mandate) {
                        $userBatch->user->setAttribute('mandate', $mandate);
                    }
                }

                if ($location->payment_gateway_id === PaymentGateway::STRIPE_CONNECT->value) {
                    $stripeConnectService = new StripeConnectService();

                    $paymentToken = (new PaymentTokenService())->getFinancePaymentToken($stripeConnectService->getDebitOrderPaymentMethodsForTenant($location->tenant), FinancePaymentTokenType::USER, $userBatch->user, $location);

                    if ($paymentToken instanceof FinancePaymentToken && $paymentToken->setup_intent_id) {
                        $setupIntent = $stripeConnectService->getSetupIntent($location, $paymentToken->setup_intent_id, ['expand' => ['mandate']]);

                        if ($setupIntent->mandate) {
                            $userBatch->user->setAttribute('stripe_mandate', $setupIntent->mandate);
                        }
                    }
                }
            }
        }

        return UserBatchResource::collection($userBatches);
    }

    #[BodyParam('user_id', 'integer', required: true)]
    #[BodyParam('debit_batch_id', 'integer', required: true)]
    public function store(CreateUserBatchRequest $request)
    {
        $debitBatch = DebitBatch::findOrFail($request->debit_batch_id);
        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user_id, $debitBatch->location->tenant_id);

        if (! $tenantUser instanceof TenantUser) {
            abort(Response::HTTP_BAD_REQUEST, 'User facility membership could not be found.');
        }

        $locationUser = (new TenantUserService())->getLocationUserByTenant($tenantUser->user, $tenantUser->tenant);

        if (! $locationUser instanceof LocationUser || $locationUser->location_id !== $debitBatch->location_id) {
            abort(Response::HTTP_BAD_REQUEST, 'Please make sure that user belongs to the location of this debit batch.');
        }

        if ($tenantUser->debit_status !== UserDebitStatus::DEBIT_ORDER) {
            abort(Response::HTTP_BAD_REQUEST, 'Please make sure user\'s payment type is set to debit-order.');
        }

        if (! $this->debitBatchService->shouldTenantUserBeAddedToDebitBatch($tenantUser, $debitBatch)) {
            $errorMessage = $this->debitBatchService->shouldTenantUserBeAddedToDebitBatch($tenantUser, $debitBatch, true);

            abort(Response::HTTP_BAD_REQUEST, 'User cannot be added to batch: '.$errorMessage);
        }

        if ($debitBatch->is_processed) {
            abort(Response::HTTP_BAD_REQUEST, 'The debit batch selected has already been processed.');
        }

        $invoice = (new FinanceService())->generateInvoiceForUser(user: $tenantUser->user, debitBatch: $debitBatch);

        if (! $invoice instanceof UserInvoice) {
            abort(Response::HTTP_BAD_REQUEST, 'Something went wrong while generating the invoice.');
        }

        $userBatch = $this->debitBatchService->createUserBatch($tenantUser->user, $invoice, $debitBatch, true);

        if (! $userBatch instanceof UserBatch) {
            abort(Response::HTTP_BAD_REQUEST, 'Something went wrong while generating the user batch.');
        }

        $this->debitBatchService->updateBatchTotal($debitBatch);

        return new UserBatchResource($userBatch);
    }
}
