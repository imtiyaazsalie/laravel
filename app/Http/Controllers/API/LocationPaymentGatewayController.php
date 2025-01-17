<?php

namespace App\Http\Controllers\API;

use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\LocationPaymentGateway\CreateAdHocSettingsRequest;
use App\Http\Requests\LocationPaymentGateway\ListAdHocSettingsRequest;
use App\Http\Requests\LocationPaymentGateway\ListGoCardlessDetailsRequest;
use App\Http\Requests\LocationPaymentGateway\ListNetcashDebitOrderDetailsRequest;
use App\Http\Requests\LocationPaymentGateway\ListSepaDebitOrderDetailsRequest;
use App\Http\Requests\LocationPaymentGateway\ListStripeConnectDebitOrderDetailsRequest;
use App\Http\Requests\LocationPaymentGateway\ListThreePeaksDebitOrderDetailsRequest;
use App\Http\Requests\LocationPaymentGateway\UpdateAdHocSettingsRequest;
use App\Http\Requests\LocationPaymentGateway\UpdateNetcashDebitOrderSettingsRequest;
use App\Http\Requests\LocationPaymentGateway\UpdateSepaDebitOrderSettingsRequest;
use App\Http\Requests\LocationPaymentGateway\UpdateThreePeaksDebitOrderSettingsRequest;
use App\Http\Resources\LocationPaymentGatewayGoCardlessDetailsList;
use App\Http\Resources\LocationPaymentGatewayResource;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\Tenant;
use App\Services\PaymentGateways\PaymentGatewayService;
use App\Services\PaymentGateways\StripeConnectService;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\QueryParam;
use Symfony\Component\HttpFoundation\Response;

class LocationPaymentGatewayController extends Controller
{
    public function __construct(public PaymentGatewayService $paymentGatewayService)
    {
    }

    public function getGoCardlessDetails(ListGoCardlessDetailsRequest $request)
    {
        $locations = Location::query()->active()
            ->where('box_id', $request->tenant_id)
            ->where('payment_gateway_id', PaymentGateway::GO_CARDLESS)
            ->when($request->location_id, function ($query) use ($request) {
                $query->where('box_facility_id', $request->location_id);
            })
            ->get();

        return response()->json(
            $locations->map(
                fn ($location) => new LocationPaymentGatewayGoCardlessDetailsList($location)
            )->toArray()
        );
    }

    public function getStripeConnectDetails(ListStripeConnectDebitOrderDetailsRequest $request)
    {
        $locations = Location::query()->active()
            ->where('box_id', $request->tenant_id)
            ->where('payment_gateway_id', PaymentGateway::STRIPE_CONNECT)
            ->when($request->location_id, function ($query) use ($request) {
                $query->where('box_facility_id', $request->location_id);
            })
            ->get();

        $stripeConnectService = (new StripeConnectService());

        return response()->json(
            $locations->map(fn ($location) => [
                'location' => [
                    'id' => $location->getKey(),
                    'name' => $location->name,
                ],
                'onboarding_status' => $stripeConnectService->getLocationOnboardingStatus($location),
                'has_onboarded' => $stripeConnectService->getLocationOnboardingStatus($location) === 'onboardingComplete',
            ])->toArray()
        );
    }

    public function getDebitOrderNetcashDetails(ListNetcashDebitOrderDetailsRequest $request)
    {
        $locations = Location::query()->active()
            ->where('box_id', $request->tenant_id)
            ->where('payment_gateway_id', PaymentGateway::SAGE_PAY_V3)
            ->when($request->location_id, function ($query) use ($request) {
                $query->where('box_facility_id', $request->location_id);
            })
            ->get();

        $debitOrderSettings = collect();

        foreach ($locations as $location) {
            $locationPaymentGateway = $this->paymentGatewayService->getActiveLocationPaymentGateway($location, PaymentGateway::SAGE_PAY_V3, PaymentGatewayContext::DEBIT_ORDER);

            if (! $locationPaymentGateway?->settings) {
                $locationPaymentGateway = $this->paymentGatewayService->createOrUpdateLocationPaymentGateway($location, PaymentGateway::SAGE_PAY_V3, $locationPaymentGateway, PaymentGatewayContext::DEBIT_ORDER);
            }

            $locationPaymentGateway->setAttribute('location_payment_gateway_settings', $locationPaymentGateway->settings);
            $locationPaymentGateway->setRelation('location', $location);

            $debitOrderSettings->add($locationPaymentGateway);
        }

        return LocationPaymentGatewayResource::collection($debitOrderSettings);
    }

    public function putNetcashDebitOrderDetails(UpdateNetcashDebitOrderSettingsRequest $request, LocationPaymentGateway $locationPaymentGateway)
    {
        if ($locationPaymentGateway->payment_gateway_id !== PaymentGateway::SAGE_PAY_V3->value && $locationPaymentGateway->context !== PaymentGatewayContext::DEBIT_ORDER->value) {
            abort(Response::HTTP_BAD_REQUEST, 'This location payment gateway is not a debit-order Netcash payment gateway');
        }

        if (! $locationPaymentGateway->settings) {
            $this->paymentGatewayService->createOrUpdateLocationPaymentGateway($locationPaymentGateway->location, PaymentGateway::SAGE_PAY_V3, $locationPaymentGateway, PaymentGatewayContext::DEBIT_ORDER);
        }

        $locationPaymentGateway->settings->update([
            'merchant_account_number' => $request->get('merchant_account_number'),
            'account_service_key' => $request->get('account_service_key'),
            'debit_order_service_key' => $request->get('debit_order_service_key'),
        ]);

        $credentials = [
            0 => $locationPaymentGateway->settings->merchant_account_number,
            1 => $locationPaymentGateway->settings->account_service_key,
            2 => $locationPaymentGateway->settings->debit_order_service_key,
        ];

        $locationPaymentGateway->update(['credentials' => implode('&', $credentials)]);

        $locationPaymentGateway->setAttribute('location_payment_gateway_settings', $locationPaymentGateway->settings);

        return new LocationPaymentGatewayResource($locationPaymentGateway);
    }

    public function getDebitOrderThreePeaksDetails(ListThreePeaksDebitOrderDetailsRequest $request)
    {
        $locations = Location::query()->active()
            ->where('box_id', $request->tenant_id)
            ->where('payment_gateway_id', PaymentGateway::THREE_PEAKS)
            ->when($request->location_id, function ($query) use ($request) {
                $query->where('box_facility_id', $request->location_id);
            })
            ->get();

        $debitOrderSettings = collect();

        foreach ($locations as $location) {
            $locationPaymentGateway = $this->paymentGatewayService->getActiveLocationPaymentGateway($location, PaymentGateway::THREE_PEAKS, PaymentGatewayContext::DEBIT_ORDER);

            if (! $locationPaymentGateway) {
                $locationPaymentGateway = $this->paymentGatewayService->createOrUpdateLocationPaymentGateway($location, PaymentGateway::THREE_PEAKS, $locationPaymentGateway, PaymentGatewayContext::DEBIT_ORDER);
            }

            $paymentGatewayCredentials = explode('&', $locationPaymentGateway->credentials);

            $locationPaymentGateway->setAttribute('location_payment_gateway_settings', [
                'dev_id' => @$paymentGatewayCredentials[0],
                'dev_token' => @$paymentGatewayCredentials[1],
                'cref' => @$paymentGatewayCredentials[2],
            ]);

            $debitOrderSettings->add($locationPaymentGateway);
        }

        return LocationPaymentGatewayResource::collection($debitOrderSettings);
    }

    public function putDebitOrderThreePeaksDetails(UpdateThreePeaksDebitOrderSettingsRequest $request, LocationPaymentGateway $locationPaymentGateway)
    {
        $credentials = [
            0 => $request->get('dev_id'),
            1 => $request->get('dev_token'),
            2 => $request->get('cref'),
        ];

        $locationPaymentGateway->update(['credentials' => implode('&', $credentials)]);

        return new LocationPaymentGatewayResource($locationPaymentGateway);
    }

    public function getAdHocSettings(ListAdHocSettingsRequest $request)
    {
        $adhocSettings = collect();

        $locations = Location::query()
            ->active()
            ->where('box_id', $request->tenant_id)
            ->when($request->location_id, function ($query) use ($request) {
                $query->where('box_facility_id', $request->location_id);
            })
            ->get();

        foreach ($locations as $location) {
            $locationPaymentGateways = $this->paymentGatewayService->getAppropriateLocationPaymentGateways(
                $location->tenant, $location, PaymentGatewayContext::AD_HOC, $request->get('is_active')
            );

            foreach ($locationPaymentGateways as $locationPaymentGateway) {
                $locationPaymentGateway->setAttribute('location_payment_gateway_settings', $locationPaymentGateway->settings);
                $locationPaymentGateway->setRelation('location', $location);
                $adhocSettings->add($locationPaymentGateway);
            }
        }

        return LocationPaymentGatewayResource::collection($adhocSettings);
    }

    public function postAdHocSettings(CreateAdHocSettingsRequest $request)
    {
        $location = Location::findOrFail($request->get('location_id'));
        $paymentGateway = PaymentGateway::from($request->get('payment_gateway_id'));

        $locationPaymentGateway = $this->paymentGatewayService->getActiveLocationPaymentGateway($location, $paymentGateway, PaymentGatewayContext::AD_HOC);

        if (! $locationPaymentGateway?->settings) {
            $locationPaymentGateway = $this->paymentGatewayService->createOrUpdateLocationPaymentGateway($location, $paymentGateway, $locationPaymentGateway, PaymentGatewayContext::AD_HOC);
        }

        $this->paymentGatewayService->updateAdhocSettings($locationPaymentGateway, $request);

        $locationPaymentGateway->setAttribute('location_payment_gateway_settings', $locationPaymentGateway->settings);

        return new LocationPaymentGatewayResource($locationPaymentGateway);
    }

    public function putAdhocSettings(UpdateAdHocSettingsRequest $request, LocationPaymentGateway $locationPaymentGateway)
    {
        $this->paymentGatewayService->updateAdhocSettings($locationPaymentGateway, $request);

        $locationPaymentGateway->update(['is_active' => $request->get('is_active')]);
        $locationPaymentGateway->setAttribute('location_payment_gateway_settings', $locationPaymentGateway->settings);

        return new LocationPaymentGatewayResource($locationPaymentGateway);
    }

    #[QueryParam('tenant_id', 'int', required: true)]
    public function getDebitOrderSepaSettings(ListSepaDebitOrderDetailsRequest $request)
    {
        $tenant = Tenant::findOrFail($request->get('tenant_id'));

        $locationPaymentGateways = collect();

        $locations = $tenant->locations()
            ->where('payment_gateway_id', '=', PaymentGateway::SEPA)
            ->when($request->location_id, function ($query) use ($request) {
                $query->where('box_facility_id', $request->location_id);
            })
            ->get();

        foreach ($locations as $location) {
            $locationPaymentGateway = $this->paymentGatewayService->getActiveLocationPaymentGateway($location, PaymentGateway::SEPA, PaymentGatewayContext::DEBIT_ORDER);

            if (! $locationPaymentGateway?->settings) {
                $locationPaymentGateway = $this->paymentGatewayService->createOrUpdateLocationPaymentGateway($location, PaymentGateway::SEPA, $locationPaymentGateway, PaymentGatewayContext::DEBIT_ORDER);
            }

            $locationPaymentGateway->setAttribute('location_payment_gateway_settings', $locationPaymentGateway->settings);

            $locationPaymentGateways->add($locationPaymentGateway);
        }

        return LocationPaymentGatewayResource::collection($locationPaymentGateways);
    }

    #[BodyParam('creditor_id', 'string', required: true)]
    #[BodyParam('creditor_iban', 'string', required: true)]
    #[BodyParam('creditor_bic', 'string', required: true)]
    public function putDebitOrderSepaSettings(UpdateSepaDebitOrderSettingsRequest $request, LocationPaymentGateway $locationPaymentGateway)
    {
        $locationPaymentGateway->settings->update($request->safe()->toArray());

        $locationPaymentGateway->setAttribute('location_payment_gateway_settings', $locationPaymentGateway->settings);

        return new LocationPaymentGatewayResource($locationPaymentGateway);
    }
}
