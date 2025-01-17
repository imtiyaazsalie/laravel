<?php

namespace App\Models;

use App\Traits\RecordUserOnCreateAndUpdate;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPaymentGatewaySettings extends Model
{
    use HasFactory, RecordUserOnCreateAndUpdate;

    protected $table = 'facility_payment_gateway_settings';

    protected $primaryKey = 'setting_id';

    public $timestamps = true;

    public const CREATED_AT = null;

    public const UPDATED_AT = 'modified_on';

    public const CREATED_BY_ID = null;

    public const UPDATED_BY_ID = 'modified_id';

    protected $guarded = [];

    /**
     * The "booted" method of the model.
     */
    public function locationPaymentGateway(): BelongsTo
    {
        return $this->belongsTo(LocationPaymentGateway::class, 'box_facility_payment_gateway_id');
    }

    /**
     * Mutate box_facility_payment_gateway_id to location_payment_gateway_id
     */
    protected function locationPaymentGatewayId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_facility_payment_gateway_id,
            set: fn (mixed $value) => ['box_facility_payment_gateway_id' => $value]
        );
    }
}
