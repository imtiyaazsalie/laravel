<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Region\CreateRegionRequest;
use App\Http\Requests\Region\DeleteRegionRequest;
use App\Http\Requests\Region\ListRegionsRequest;
use App\Http\Requests\Region\ToggleRegionStatusRequest;
use App\Http\Requests\Region\UpdateRegionRequest;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Models\RegionCurrency;
use App\Models\RegionTimezone;
use App\Models\Tenant;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class RegionController extends Controller
{
    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    public function list(ListRegionsRequest $request)
    {
        $regions = QueryBuilder::for(Region::class)
            ->allowedFilters([
                AllowedFilter::exact('is_active'),
            ])
            ->allowedIncludes(['timezones', 'currencies'])
            ->allowedSorts([
                AllowedSort::field('name', 'region_desc'),
            ])
            ->_paginate()
            ->appends($request->query());

        return RegionResource::collection($regions);
    }

    public function store(CreateRegionRequest $request)
    {
        $region = Region::create($request->only(['name', 'country_code_iso2']));

        foreach ($request->get('currencies') as $item) {
            RegionCurrency::updateOrCreate(
                [
                    'region_id' => $region->getKey(),
                    'currency_id' => $item,
                ],
                [
                    'region_id' => $region->getKey(),
                    'currency_id' => $item,
                ]
            );
        }

        foreach ($request->get('timezones') as $item) {
            RegionTimezone::updateOrCreate(
                [
                    'region_id' => $region->getKey(),
                    'timezone_id' => $item,
                ],
                [
                    'region_id' => $region->getKey(),
                    'timezone_id' => $item,
                ]
            );
        }

        return new RegionResource($region->load(['timezones', 'currencies']));
    }

    public function update(Region $region, UpdateRegionRequest $request)
    {
        $region->update($request->only(['name', 'country_code_iso2']));

        $region->currencies()->detach();
        $region->timezones()->detach();

        $region->currencies()->attach($request->get('currencies'));
        $region->timezones()->attach($request->get('timezones'));

        return new RegionResource($region->load(['timezones', 'currencies']));
    }

    public function toggleStatus(ToggleRegionStatusRequest $request, Region $region)
    {
        $region->toggleStatus();

        return new RegionResource($region);
    }

    public function delete(DeleteRegionRequest $request, Region $region)
    {
        $region->currencies()->detach();
        $region->timezones()->detach();

        if (Tenant::query()->where('region_id', $region->getKey())->exists()) {
            return response()->json([
                'message' => 'Cannot delete region with associated tenants.',
            ], 400);
        }

        $region->delete();

        return response()->noContent();
    }
}