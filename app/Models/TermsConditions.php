<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermsConditions extends Model
{
    use HasFactory;

    protected $table = 'terms_and_conditions';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = null;

    protected $casts = [
        'released_on' => 'datetime',
    ];

    protected $guarded = [];
}
