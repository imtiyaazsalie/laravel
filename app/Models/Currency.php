<?php

namespace App\Models;

use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory, Paginatable;

    protected $table = 'currencies';

    protected $primaryKey = 'currency_id';

    protected $guarded = [];

    public $timestamps = false;
}
