<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeMandate extends Model
{
    use HasFactory;

    protected $primaryKey = 'mandate_id';

    protected $table = 'stripe_mandates';

    protected $guarded = [];

    public $timestamps = true;
}
