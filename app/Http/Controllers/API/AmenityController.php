<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Amenities\CreateAmenityRequest;
use App\Http\Requests\Amenities\DeleteAmenityRequest;
use App\Http\Requests\Amenities\ListAmenitiesRequest;
use App\Http\Requests\Amenities\ShowAmenityRequest;
use App\Http\Requests\Amenities\UpdateAmenityRequest;
use App\Http\Resources\AmenityResource;
use App\Models\Amenity;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AmenityController extends Controller
{
    #[QueryParam('filter[name]', 'string', null, false)]
    public function index(ListAmenitiesRequest $request)
    {
        return AmenityResource::collection(
            QueryBuilder::for(Amenity::class)
                ->allowedIncludes('locations')
                ->allowedFilters(
                    AllowedFilter::partial('name')
                )->_paginate()
        );
    }

    public function store(CreateAmenityRequest $request): AmenityResource
    {
        $amenity = Amenity::query()->create($request->safe()->all());

        return new AmenityResource($amenity);
    }

    public function show(ShowAmenityRequest $request, Amenity $amenity): AmenityResource
    {
        return new AmenityResource($amenity);
    }

    public function update(Amenity $amenity, UpdateAmenityRequest $request): AmenityResource
    {
        $amenity->update($request->safe()->all());

        return new AmenityResource($amenity);
    }

    public function destroy(DeleteAmenityRequest $request, Amenity $amenity): Response
    {
        $amenity->delete();

        return response()->noContent();
    }
}
