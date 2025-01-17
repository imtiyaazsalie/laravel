<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeasuringUnit\CreateMeasuringUnitRequest;
use App\Http\Requests\MeasuringUnit\DeleteMeasuringUnitRequest;
use App\Http\Requests\MeasuringUnit\ListMeasuringUnitsRequest;
use App\Http\Requests\MeasuringUnit\ToggleMeasuringUnitStatusRequest;
use App\Http\Requests\MeasuringUnit\UpdateMeasuringUnitRequest;
use App\Http\Resources\MeasurementUnitResource;
use App\Models\MeasurementUnit;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MeasuringUnitController extends Controller
{
    public function store(CreateMeasuringUnitRequest $request)
    {
        $measureUnit = new MeasurementUnit();
        $measureUnit->fill($request->safe()->except('tenant_id'));
        $measureUnit->is_active = true;
        $measureUnit->save();

        return new MeasurementUnitResource($measureUnit);
    }

    public function update(MeasurementUnit $measurementUnit, UpdateMeasuringUnitRequest $request)
    {
        $measurementUnit->update($request->safe()->except('tenant_id'));

        return new MeasurementUnitResource($measurementUnit);
    }

    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    public function list(ListMeasuringUnitsRequest $request)
    {
        return MeasurementUnitResource::collection(
            QueryBuilder::for(MeasurementUnit::class)
                ->allowedFilters([
                    AllowedFilter::exact('is_active', 'is_active'),
                ])
                ->_paginate()
        );
    }

    public function delete(MeasurementUnit $measurementUnit, DeleteMeasuringUnitRequest $request)
    {
        $measurementUnit->delete();

        return response()->noContent();
    }

    public function toggleStatus(MeasurementUnit $measurementUnit, ToggleMeasuringUnitStatusRequest $request)
    {
        $measurementUnit->toggleStatus();

        return new MeasurementUnitResource($measurementUnit);

    }
}
