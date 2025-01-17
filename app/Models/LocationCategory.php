<?php

namespace App\Models;

use App\Traits\MutatesBoxFacilityId;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationCategory extends Model
{
    use HasFactory, MutatesBoxFacilityId, Paginatable;

    protected $table = 'box_facility_categories';

    protected $primaryKey = 'box_facility_category_id';

    public $timestamps = false;

    protected $guarded = [];
}
