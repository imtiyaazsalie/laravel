<?php

namespace App\Models;

use App\Enums\PackageType;
use App\Traits\BelongsToTenant;
use App\Traits\HasTags;
use App\Traits\Paginatable;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Package extends Model
{
    use BelongsToTenant, HasFactory, HasTags, Paginatable;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    public $timestamps = true;

    protected $table = 'packages';

    protected $primaryKey = 'package_id';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'integer',
        'is_displayed' => 'integer',
        'is_display_on_buy_packages' => 'integer',
        'package_limit_type_id' => PackageType::class,
        'package_limit' => 'integer',
        'package_price' => 'float',
        'package_topup_price' => 'float',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Mutate package_limit_type_id to type
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->package_limit_type_id,
            set: fn (mixed $value) => ['package_limit_type_id' => $value]
        );
    }

    protected function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_id,
            set: fn (mixed $value) => ['box_id' => $value]
        );
    }

    /**
     * Mutate package_name to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->package_name,
            set: fn (mixed $value) => ['package_name' => $value]
        );
    }

    /**
     * Mutate package_limit to limit
     */
    protected function limit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->package_limit,
            set: fn (mixed $value) => ['package_limit' => $value]
        );
    }

    /**
     * Mutate package_descr to description
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->package_descr,
            set: fn (mixed $value) => ['package_descr' => $value]
        );
    }

    /**
     * Mutate package_price to price
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->package_price,
            set: fn (mixed $value) => ['package_price' => $value]
        );
    }

    /**
     * Mutate package_topup_price to topup_price
     */
    protected function topupPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->package_topup_price,
            set: fn (mixed $value) => ['package_topup_price' => $value]
        );
    }

    public function toggleStatus()
    {
        $this->update(['is_active' => ! $this->is_active]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'box_id');
    }

    public function classes(): HasManyThrough
    {
        return $this->hasManyThrough(Classes::class, ClassPackage::class, 'package_id', 'class_id', 'package_id', 'class_id');
    }

    public function programmes(): HasManyThrough
    {
        return $this->hasManyThrough(Programme::class, ProgrammePackageVisibility::class, 'package_id', 'id', 'package_id', 'programme_id');
    }

    public function programmeVisibility(): HasMany
    {
        return $this->hasMany(ProgrammePackageVisibility::class, 'package_id');
    }

    public function locations(): HasManyThrough
    {
        return $this->hasManyThrough(Location::class, LocationPackage::class, 'package_id', 'box_facility_id', 'package_id', 'box_facility_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isWeekly(): bool
    {
        return $this->package_limit_type_id === PackageType::WEEKLY;
    }

    public function isMonthly(): bool
    {
        return $this->package_limit_type_id === PackageType::MONTHLY;
    }

    public function isLimited(): bool
    {
        return $this->package_limit_type_id === PackageType::LIMITED;
    }

    public function isNotLimited(): bool
    {
        return ! $this->isLimited();
    }

    public function hasLimitedSessions(): bool
    {
        return $this->type === PackageType::LIMITED || $this->type === PackageType::DROP_IN;
    }

    public function getPrice(): string
    {
        return $this->package_price;
    }

    public function getDefaultPeriodInterval(?Carbon $start = null): ?Carbon
    {
        if (! $this->default_period_interval || in_array($this->default_period_interval, [
            'P0W',
            'P0M',
            'P0Y',
            'P0D',
            'P',
        ])) {
            return null;
        }

        if (! $interval = CarbonInterval::make($this->default_period_interval)) {
            return null;
        }

        return $start?->add($interval) ?: now()->add($interval);
    }

    public function getProRateAmount()
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) date('m'), (int) date('Y'));
        $daysRemainingInMonth = $daysInMonth - (int) (date('d', strtotime('yesterday')));
        $ratePerDay = round($this->package_price / $daysInMonth, 2);

        return $ratePerDay * $daysRemainingInMonth;
    }

    public function getDefaultPeriodIntervalDate(): ?\DateTime
    {
        if (! $this->default_period_interval || $this->default_period_interval === 'P0M') {
            return null;
        }

        $today = new \DateTime('today');

        try {
            return $today->add(new \DateInterval($this->default_period_interval));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getExclusiveProrateAmount(): float|int
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) date('m'), (int) date('Y'));
        $daysRemainingInMonth = $daysInMonth - (int) (date('d', strtotime('yesterday')));
        $ratePerDay = round($this->package_price / $daysInMonth, 2);

        return $ratePerDay * $daysRemainingInMonth;
    }
}
