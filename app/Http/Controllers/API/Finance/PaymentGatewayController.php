<?php

namespace App\Http\Controllers\API\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PaymentGateway\DeletePayfastDetailsRequest;
use App\Http\Requests\Finance\PaymentGateway\ListPaymentGatewaysRequest;
use App\Http\Resources\LocationResource;
use App\Models\FinancePaymentGateway;
use App\Models\Tenant;
use App\Services\PaymentGateways\PayFastService;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\QueryParam;

class PaymentGatewayController extends Controller
{
    public function __construct(private readonly PayFastService $payFastService)
    {
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    public function getPayfastDetails(ListPaymentGatewaysRequest $request)
    {
        $payfastDetails = [];
        $tenant = Tenant::findOrFail($request->input('tenant_id'));
        $locations = $tenant->locations()->get();

        foreach ($locations as $index => $location) {
            $payfastDetails[$index] = [
                'location' => new LocationResource($location),
                'has_onboarded' => true,
                'onboarding_form' => null,
            ];

            $locationPayfastDetails = FinancePaymentGateway::query()
                ->where('box_facility_id', '=', $location->getKey())
                ->where('gateway', '=', 'payfast')
                ->first();

            if ($locationPayfastDetails instanceof FinancePaymentGateway) {
                $payfastDetails[$index]['payfast_gateway_id'] = $locationPayfastDetails->getKey();
            } else {
                $payfastDetails[$index] = array_merge($payfastDetails[$index], [
                    'has_onboarded' => false,
                    'onboarding_form' => $this->payFastService->getOnboardingForm($location),
                ]);
            }
        }

        return response($payfastDetails);
    }

    #[BodyParam('location_id', 'integer', required: true)]
    public function deletePayfastDetails(DeletePayfastDetailsRequest $request)
    {
        FinancePaymentGateway::query()
            ->where('box_facility_id', '=', $request->get('location_id'))
            ->where('deleted', '=', false)
            ->where('gateway', '=', 'payfast')
            ->delete();

        return response()->noContent();
    }
}
