<?php

namespace App\Models;

use App\Traits\MutatesBoxFacilityId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationPackage extends Model
{
    use HasFactory, MutatesBoxFacilityId;

    public $timestamps = false;

    protected $table = 'package_to_box_facility';

    protected $guarded = [];

    public function location()
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }
}
