<?php

namespace App\Models;

use App\Traits\MutatesBoxFacilityId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LocationHealthProvider extends Pivot
{
    use HasFactory, MutatesBoxFacilityId;

    protected $table = 'box_facilities_to_health_providers';

    public $timestamps = false;

    protected $guarded = [];
}
