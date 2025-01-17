<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionTimezone extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'regions_to_timezones';

    protected $guarded = [];
}
