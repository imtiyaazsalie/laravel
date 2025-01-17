<?php

namespace App\Models;

use App\Enums\MandateStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MandateGoCardless extends Model
{
    use BelongsToTenant;

    protected $table = 'go_cardless_mandates';

    protected $primaryKey = 'mandate_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    protected $guarded = [];

    protected $attributes = [
        'status' => MandateStatus::PENDING,
    ];

    protected $casts = [
        'status' => MandateStatus::class,
        'cancelled_at' => 'datetime',
    ];

    protected function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_id,
            set: fn (mixed $value) => ['box_id' => $value]
        );
    }

    /**
     * Mutate facility_payment_gateway_id to location_payment_gateway_id
     */
    protected function locationPaymentGatewayId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->facility_payment_gateway_id,
            set: fn (mixed $value) => ['facility_payment_gateway_id' => $value]
        );
    }

    public function locationPaymentGateway(): BelongsTo
    {
        return $this->belongsTo(LocationPaymentGateway::class, 'facility_payment_gateway_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function leadMember(): BelongsTo
    {
        return $this->belongsTo(LeadMember::class, 'member_id');
    }
}
