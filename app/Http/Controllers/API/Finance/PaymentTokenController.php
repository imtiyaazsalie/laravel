<?php

namespace App\Http\Controllers\API\Finance;

use App\Enums\FinancePaymentTokenType;
use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\LocationPaymentToken\DeleteLocationPaymentToken;
use App\Http\Requests\Finance\LocationPaymentToken\ListLocationPaymentTokensRequest;
use App\Http\Resources\FinancePaymentTokenResource;
use App\Http\Resources\LocationResource;
use App\Models\FinancePaymentToken;
use App\Models\Tenant;
use App\Services\PaymentGateways\PayFastService;
use App\Services\PaymentTokenService;

class PaymentTokenController extends Controller
{
    public function listLocationPaymentTokens(ListLocationPaymentTokensRequest $request)
    {
        $locationPaymentTokens = [];
        $tenant = Tenant::findOrFail($request->input('tenant_id'));
        $locations = $tenant->locations()->get();

        foreach ($locations as $index => $location) {
            $locationPaymentToken = (new PaymentTokenService())->getFinancePaymentToken(
                paymentMethods: ['card'],
                financePaymentTokenType: FinancePaymentTokenType::LOCATION,
                location: $location,
                paymentGateway: $location->billing_payment_gateway_id
            );

            $locationPaymentTokens[$index] = [
                'location' => new LocationResource($location),
                'payment_token' => $locationPaymentToken ? new FinancePaymentTokenResource($locationPaymentToken) : null,
            ];

            if (! $locationPaymentToken instanceof FinancePaymentToken && $location->billing_payment_gateway_id === PaymentGateway::PAYFAST) {
                $locationPaymentTokens[$index] = array_merge($locationPaymentTokens[$index], [
                    'onboarding_form' => (new PayFastService())->getOnboardingForm($location),
                ]);
            }
        }

        return response($locationPaymentTokens);
    }

    public function delete(DeleteLocationPaymentToken $request, FinancePaymentToken $financePaymentToken)
    {
        $financePaymentToken->delete();

        return response()->noContent();
    }
}
