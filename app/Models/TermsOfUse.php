<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermsOfUse extends Model
{
    use HasFactory;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = null;

    public $timestamps = true;

    protected $table = 'terms_of_use';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'released_on' => 'datetime',
    ];
}
