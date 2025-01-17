<?php

namespace App\Models;

use App\Enums\PaymentGateway as EnumPaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Traits\MutatesBoxFacilityId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;

class LocationPaymentGateway extends Model
{
    use HasFactory, MutatesBoxFacilityId;

    protected $table = 'box_facility_to_payment_gateway';

    protected $primaryKey = 'facility_to_payment_gateway_id';

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $guarded = [];

    protected $casts = [
        // TODO: 'credentials' => \App\ThirdParty\GatewayCredentials::class
        'is_active' => 'integer',
    ];

    protected $attributes = [
        'is_active' => 1,
    ];

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(LocationPaymentGatewaySettings::class, 'box_facility_payment_gateway_id', 'facility_to_payment_gateway_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }

    public function scopeGoCardless(Builder $builder): Builder
    {
        return $builder->where('payment_gateway_id', EnumPaymentGateway::GO_CARDLESS->value);
    }

    public function scopeAdHoc(Builder $builder): Builder
    {
        return $builder->where('context', PaymentGatewayContext::AD_HOC->value);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isGoCardless(): bool
    {
        return $this->payment_gateway_id === EnumPaymentGateway::GO_CARDLESS;
    }

    public function isStripe(): bool
    {
        return $this->payment_gateway_id === EnumPaymentGateway::STRIPE;
    }

    public function isSage(): bool
    {
        return $this->payment_gateway_id === EnumPaymentGateway::SAGE_PAY_V3;
    }

    public function isThreePeaks(): bool
    {
        return $this->payment_gateway_id === EnumPaymentGateway::THREE_PEAKS;
    }

    public function isNoGateway(): bool
    {
        return $this->payment_gateway_id === EnumPaymentGateway::NO_GATEWAY;
    }

    public function isPaystack(): bool
    {
        return $this->payment_gateway_id === EnumPaymentGateway::PAYSTACK;
    }

    public function getServiceKeyCredential(): ?string
    {
        $options = explode('&', $this->credentials);

        return Arr::get($options, 2);
    }

    public function getThreePeaksCredentials(): array
    {
        $credentials = explode('&', $this->credentials);

        if (! app()->environment('production')) {
            return [
                'development_id' => 90,
                'development_token' => '12695ec5f3ed7ad96337311eae60b6ca',
                'reference' => 'OCTIV',
            ];
        }

        return [
            'development_id' => Arr::get($credentials, 0),
            'development_token' => Arr::get($credentials, 1),
            'reference' => Arr::get($credentials, 2),
        ];
    }
}
