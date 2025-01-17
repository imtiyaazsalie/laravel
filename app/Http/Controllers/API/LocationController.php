<?php

namespace App\Http\Controllers\API;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\CreateLocationRequest;
use App\Http\Requests\Location\DeleteLocationRequest;
use App\Http\Requests\Location\ExportLocationParametersRequest;
use App\Http\Requests\Location\GetLocationAttendanceCodeRequest;
use App\Http\Requests\Location\ListLocationRequest;
use App\Http\Requests\Location\ProcessPaymentsRequest;
use App\Http\Requests\Location\ReadLocationRequest;
use App\Http\Requests\Location\UpdateLocationParametersRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Jobs\ProcessLocationBillingPayments;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class LocationController extends Controller
{
    public function __construct(private readonly LocationService $locationService)
    {
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[region_id]', 'integer', required: false)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[payment_gateway_id]', 'integer', required: false)]
    #[QueryParam('filter[billing_payment_gateway_id]', 'integer', required: false)]
    #[QueryParam('filter[tenant_status_id]', 'integer', required: false)]
    #[QueryParam('filter[health_provider_id]', 'integer', required: false)]
    #[QueryParam('filter[amenity_id]', 'integer', required: false)]
    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    #[QueryParam('filter[search]', 'string', required: false)]
    #[QueryParam('page', 'string', required: false)]
    #[QueryParam('per_page', 'string', required: false)]
    public function list(ListLocationRequest $request): AnonymousResourceCollection
    {
        $locations = QueryBuilder::for(Location::class)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::exact('region_id', 'tenant.region_id'),
                AllowedFilter::exact('location_id', 'box_facility_id'),
                AllowedFilter::exact('billing_payment_gateway_id', 'billing_payment_gateway_id'),
                AllowedFilter::exact('payment_gateway_id', 'payment_gateway_id'),
                AllowedFilter::exact('tenant_status_id', 'tenant.box_status_id')->default(TenantStatus::ACTIVE),
                AllowedFilter::exact('health_provider_id', 'locationHealthProviders.health_provider_id'),
                AllowedFilter::exact('amenity_id', 'amenities.id'),
                AllowedFilter::exact('is_active', 'is_active')->default(true),
                AllowedFilter::partial('search', 'box_facility_name'),
            ])
            ->allowedIncludes([
                AllowedInclude::relationship('healthProviders'),
                AllowedInclude::relationship('tenant'),
                AllowedInclude::relationship('tenant.region'),
                AllowedInclude::relationship('tenant.tenantCurrency'),
                AllowedInclude::relationship('tenant.headCoaches'),
                AllowedInclude::relationship('billingPaymentGateway'),
                AllowedInclude::relationship('paymentGateway'),
                AllowedInclude::relationship('amenities'),
                AllowedInclude::relationship('category'),
                AllowedInclude::relationship('addresses'),
                AllowedInclude::relationship('locationPaymentGateways.settings'),
                AllowedInclude::relationship('timezone'),
            ])
            ->defaultSort('box_facility.box_facility_name')
            ->_paginate();

        return LocationResource::collection($locations);
    }

    public function show(ReadLocationRequest $request, Location $location): LocationResource
    {
        return new LocationResource($location->loadMissing(['category', 'tenant', 'operatingHours', 'addresses', 'amenities']));
    }

    public function store(CreateLocationRequest $request): LocationResource
    {
        return new LocationResource($this->locationService->createFromRequest($request));
    }

    public function update(UpdateLocationRequest $request, Location $location): LocationResource
    {
        return new LocationResource($this->locationService->updateFromRequest($location, $request));
    }

    public function delete(DeleteLocationRequest $request, Location $location): void
    {
        $this->locationService->delete($location);
    }

    public function processPayments(ProcessPaymentsRequest $request)
    {
        foreach ($request->input('billing_amounts') as $billingAmount) {
            $location = Location::find($billingAmount['location_id']);

            if (! $location) {
                continue;
            }

            ProcessLocationBillingPayments::dispatch($location, (float) $billingAmount['amount'])->onQueue('finances');
        }

        return response(null, Response::HTTP_OK);
    }

    public function getExportParameters(ExportLocationParametersRequest $request, Location $location)
    {
        return response()->json([
            'data' => [
                'bank_user_code' => Arr::get($location->extra_parameters, 'bank_user_code'),
                'bank_user_name' => Arr::get($location->extra_parameters, 'bank_user_name'),
                'bank_nominated_account' => Arr::get($location->extra_parameters, 'bank_nominated_account'),
            ],
        ]);
    }

    public function updateExportParameter(UpdateLocationParametersRequest $request, Location $location)
    {
        $location = $this->locationService->updateExportParametersByTenantRegion($location, $request->validated());

        return response()->json([
            'data' => [
                'bank_user_code' => Arr::get($location->extra_parameters, 'bank_user_code'),
                'bank_user_name' => Arr::get($location->extra_parameters, 'bank_user_name'),
                'bank_nominated_account' => Arr::get($location->extra_parameters, 'bank_nominated_account'),
            ],
        ]);
    }

    public function getAttendanceCode(GetLocationAttendanceCodeRequest $request, Location $location): JsonResponse
    {
        $isCodeInvalid = true;

        if ($location->attendance_code && $location->attendance_code_expires_on) {
            $to = $location->attendance_code_expires_on;
            $from = now();
            $diff = $to->diff($from);

            // Get the total diff in minutes
            $minutes = $diff->days * 24 * 60;
            $minutes += $diff->h * 60;
            $minutes += $diff->i;

            $isCodeInvalid = ($minutes <= 10 || $diff->invert !== 1);
        }

        if ($isCodeInvalid) {
            $location->update([
                'attendance_code' => Str::uuid(),
                'attendance_code_expires_on' => now()->addHour(),
            ]);
        }

        return response()->json([
            'attendance_code' => $location->attendance_code,
            'attendance_code_expires_on' => $location->attendance_code_expires_on->toDateTimeString(),
        ]);
    }
}
