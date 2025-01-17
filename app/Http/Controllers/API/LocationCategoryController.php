<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LocationCategories\CreateLocationCategoryRequest;
use App\Http\Requests\LocationCategories\DeleteLocationCategoryRequest;
use App\Http\Requests\LocationCategories\ListLocationCategoriesRequest;
use App\Http\Requests\LocationCategories\UpdateLocationCategoryRequest;
use App\Http\Resources\LocationCategoryResource;
use App\Models\LocationCategory;
use App\Services\LocationCategoryService;

class LocationCategoryController extends Controller
{
    public function __construct(
        protected LocationCategoryService $locationCategory)
    {
        //
    }

    public function list(ListLocationCategoriesRequest $request)
    {
        return LocationCategoryResource::collection($this->locationCategory->list());
    }

    public function store(CreateLocationCategoryRequest $request)
    {
        return new LocationCategoryResource($this->locationCategory->store($request->only('name')));
    }

    public function update(UpdateLocationCategoryRequest $request, LocationCategory $locationCategory)
    {
        return new LocationCategoryResource($this->locationCategory->update($request->only('name'), $locationCategory));
    }

    public function delete(DeleteLocationCategoryRequest $request, LocationCategory $locationCategory)
    {
        $this->locationCategory->delete($request->only('replacementCategoryId'), $locationCategory);

        return response()->noContent();
    }
}
