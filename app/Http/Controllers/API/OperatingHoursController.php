<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\OperatingHours\CreateOperatingHoursRequest;
use App\Http\Requests\OperatingHours\DeleteOperatingHoursRequest;
use App\Http\Requests\OperatingHours\ListOperatingHoursRequest;
use App\Http\Requests\OperatingHours\ShowOperatingHoursRequest;
use App\Http\Requests\OperatingHours\UpdateOperatingHoursRequest;
use App\Http\Resources\OperatingHourResource;
use App\Models\OperatingHour;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OperatingHoursController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListOperatingHoursRequest $request)
    {
        return OperatingHourResource::collection(
            QueryBuilder::for(OperatingHour::class)
                ->allowedFilters([
                    AllowedFilter::exact('location_id'),
                ])
                ->orderBy('opening_time')
                ->_paginate()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateOperatingHoursRequest $request)
    {
        $operatingHour = OperatingHour::create($request->validated());

        return new OperatingHourResource($operatingHour);
    }

    /**
     * Display the specified resource.
     */
    public function show(ShowOperatingHoursRequest $request, OperatingHour $operatingHour)
    {
        return new OperatingHourResource($operatingHour);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOperatingHoursRequest $request, OperatingHour $operatingHour)
    {
        $operatingHour->update($request->validated());

        return new OperatingHourResource($operatingHour);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteOperatingHoursRequest $request, OperatingHour $operatingHour)
    {
        $operatingHour->delete();

        return response()->noContent();
    }
}
