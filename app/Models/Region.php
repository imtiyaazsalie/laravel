<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Region extends Model
{
    use HasFactory, Paginatable;

    public $timestamps = false;

    protected $table = 'regions';

    protected $primaryKey = 'region_id';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Mutate region_desc to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->region_desc,
            set: fn (mixed $value) => ['region_desc' => $value]
        );
    }

    public function timezones(): BelongsToMany
    {
        return $this->belongsToMany(Timezone::class, RegionTimezone::class, 'region_id', 'timezone_id');
    }

    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class, RegionCurrency::class, 'region_id', 'currency_id');
    }

    public function toggleStatus(): void
    {
        $this->update(['is_active' => ! $this->is_active]);
    }

    public function isGoCardlessActive(): bool
    {
        return in_array($this->region_desc, ['Australia', 'Austria', 'Belgium', 'Bulgaria', 'Canada', 'Croatia', 'Cyprus', 'Czech Republic', 'Denmark', 'Finland', 'France', 'Germany', 'Hungary', 'Italy', 'Luxembourg', 'Malta', 'Netherlands', 'New Zealand', 'Norway', 'Poland', 'Portugal', 'Republic of Ireland', 'Romania', 'Slovakia', 'Slovenia', 'Spain', 'Sweden', 'Switzerland', 'United Kingdom', 'United States']);
    }

    public function getGatewaysForRegion()
    {
        if ($this->region_name === 'South Africa') {
            return [PaymentGateway::SAGE_PAY_V3->value, PaymentGateway::PAYSTACK->value];
        }

        $gateways = [PaymentGateway::STRIPE->value];

        if ($this->isGoCardlessActive()) {
            $gateways[] = PaymentGateway::GO_CARDLESS->value;
        }

        return $gateways;
    }

    public function setNameAttribute($value)
    {
        $this->attributes['region_desc'] = $value;
    }
}
