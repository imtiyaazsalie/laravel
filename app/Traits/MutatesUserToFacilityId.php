<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait MutatesUserToFacilityId
{
    /**
     * Mutate user_to_facility_id to user_location_id
     */
    protected function userLocationId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $attributes['user_to_facility_id'],
            set: fn (mixed $value) => ['user_to_facility_id' => $value]
        );
    }
}
