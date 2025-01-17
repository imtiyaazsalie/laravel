<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationCategory;
use Spatie\QueryBuilder\QueryBuilder;

class LocationCategoryService
{
    public function list()
    {
        return QueryBuilder::for(LocationCategory::class)
            ->orderBy('name')
            ->paginate();
    }

    public function store($data)
    {
        $locationCategory = new LocationCategory();
        $locationCategory->fill($data);
        $locationCategory->save();

        return $locationCategory;
    }

    public function update($data, LocationCategory $locationCategory)
    {
        $locationCategory->update($data);

        return $locationCategory;
    }

    public function delete($data, LocationCategory $locationCategory)
    {
        $locationCategory->delete();

        Location::where('box_facility_category_id', $locationCategory->getKey())
            ->update(['box_facility_category_id' => $data]);
    }
}
