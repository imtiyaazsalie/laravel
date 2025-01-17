<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CoachRate\CreateCoachRateRequest;
use App\Http\Requests\CoachRate\DeleteCoachRateRequest;
use App\Http\Requests\CoachRate\ListCoachRatesRequest;
use App\Http\Resources\CoachRateResource;
use App\Models\CoachRate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CoachRateController extends Controller
{
    public function list(ListCoachRatesRequest $request)
    {
        $coachRates = QueryBuilder::for(CoachRate::class)
            ->where('deleted', false)
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
            ])
            ->allowedSorts(['type', 'amount'])
            ->allowedIncludes('user', 'location')
            ->_paginate();

        return CoachRateResource::collection($coachRates);
    }

    public function delete(DeleteCoachRateRequest $request, CoachRate $rate)
    {
        $rate->delete();

        return response()->noContent();
    }

    public function store(CreateCoachRateRequest $request)
    {
        $coachRate = CoachRate::create($request->validated());

        return new CoachRateResource($coachRate);
    }
}
