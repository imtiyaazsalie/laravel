<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\BodyMeasurements\DeleteBodyMeasurementRequest;
use App\Http\Requests\BodyMeasurements\ListBodyMeasurementsRequest;
use App\Http\Requests\BodyMeasurements\ShowBodyMeasurementRequest;
use App\Http\Requests\BodyMeasurements\StoreBodyMeasurementRequest;
use App\Http\Requests\BodyMeasurements\UpdateBodyMeasurementRequest;
use App\Http\Resources\BodyMeasurementResource;
use App\Models\BodyMeasurements;
use App\Services\BodyMeasurementService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BodyMeasurementsController extends Controller
{
    private BodyMeasurementService $bodyMeasurement;

    public function __construct(BodyMeasurementService $bodyMeasurementService)
    {
        $this->bodyMeasurement = $bodyMeasurementService;
    }

    public function store(StoreBodyMeasurementRequest $request)
    {
        return new BodyMeasurementResource($this->bodyMeasurement->store([
            ...$request->validated(),
            'created_by_id' => auth()->user()->getAuthIdentifier(),
        ]));
    }

    public function list(ListBodyMeasurementsRequest $request)
    {
        return BodyMeasurementResource::collection(
            QueryBuilder::for(BodyMeasurements::class)
                ->allowedFilters([
                    AllowedFilter::exact('tenant_id', 'user.tenantUser.box_id'),
                    AllowedFilter::exact('location_id', 'user.userLocations.box_facility_id'),
                    AllowedFilter::exact('user_id'),
                    AllowedFilter::exact('recorded_at', 'recorded_on'),
                ])
                ->allowedIncludes(['user', 'createdBy'])
                ->_paginate()
        );
    }

    public function show(ShowBodyMeasurementRequest $request, BodyMeasurements $bodyMeasurement)
    {
        return new BodyMeasurementResource($bodyMeasurement);
    }

    public function update(UpdateBodyMeasurementRequest $request, BodyMeasurements $bodyMeasurement)
    {
        return new BodyMeasurementResource($this->bodyMeasurement->update($request->validated(), $bodyMeasurement));
    }

    public function delete(DeleteBodyMeasurementRequest $request, BodyMeasurements $bodyMeasurement)
    {
        $this->bodyMeasurement->delete($bodyMeasurement);

        return response()->noContent();
    }
}
