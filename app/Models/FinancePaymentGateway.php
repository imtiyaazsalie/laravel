<?php

namespace App\Models;

use App\Traits\MutatesBoxFacilityId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancePaymentGateway extends Model
{
    use HasFactory, MutatesBoxFacilityId;

    protected $table = 'finance_payment_gateways';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    protected $attributes = [
        'deleted' => false,
        'cancelled' => false,
        'error_state' => false,
    ];
}
