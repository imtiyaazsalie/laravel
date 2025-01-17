<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $table = 'addresses';

    protected $casts = [
        'coordinates' => 'array',
        'structured_address' => 'array',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}
