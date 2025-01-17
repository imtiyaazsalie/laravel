<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Amenity extends Model
{
    use SoftDeletes;

    public $timestamps = true;

    protected $guarded = [];

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'amenity_location', 'location_id', 'amenity_id');
    }
}
