<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Arr;

trait MutatesBoxFacilityId
{
    /**
     * Mutate box_facility_id to location_id
     */
    protected function locationId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => Arr::get($attributes, 'box_facility_id'),
            set: fn (mixed $value) => ['box_facility_id' => $value]
        );
    }
}
