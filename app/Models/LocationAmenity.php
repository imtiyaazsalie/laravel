<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LocationAmenity extends Model
{
    protected $table = 'amenity_location';

    protected $guarded = [];

    public function amenity(): HasOne
    {
        return $this->hasOne(Amenity::class, 'id', 'amenity_id');
    }

    public function location(): HasOne
    {
        return $this->hasOne(Location::class, 'box_facility_id', 'location_id');
    }
}
