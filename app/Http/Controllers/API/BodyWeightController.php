<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\BodyWeight\DeleteBodyWeightRequest;
use App\Http\Requests\BodyWeight\ListBodyWeightsRequest;
use App\Http\Requests\BodyWeight\ShowBodyWeightRequest;
use App\Http\Requests\BodyWeight\StoreBodyWeightRequest;
use App\Http\Requests\BodyWeight\UpdateBodyWeightRequest;
use App\Http\Resources\BodyWeightResource;
use App\Models\BodyWeight;
use App\Services\BodyWeightService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BodyWeightController extends Controller
{
    private BodyWeightService $bodyWeight;

    public function __construct(BodyWeightService $bodyWeight)
    {
        $this->bodyWeight = $bodyWeight;
    }

    public function store(StoreBodyWeightRequest $request)
    {
        return new BodyWeightResource($this->bodyWeight->store([
            ...$request->validated(),
            'created_by_id' => auth()->user()->getAuthIdentifier(),
        ]));
    }

    public function list(ListBodyWeightsRequest $request)
    {
        return BodyWeightResource::collection(
            QueryBuilder::for(BodyWeight::class)
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

    public function show(ShowBodyWeightRequest $request, BodyWeight $bodyWeight)
    {
        return new BodyWeightResource($bodyWeight);
    }

    public function update(UpdateBodyWeightRequest $request, BodyWeight $bodyWeight)
    {
        return new BodyWeightResource($this->bodyWeight->update($request->validated(), $bodyWeight));
    }

    public function delete(DeleteBodyWeightRequest $request, BodyWeight $bodyWeight)
    {
        $this->bodyWeight->delete($bodyWeight);

        return response()->noContent();
    }
}
