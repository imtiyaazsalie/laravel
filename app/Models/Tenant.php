<?php

namespace App\Models;

use App\Casts\Serialize;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Scopes\OwnedByTenantScope;
use App\Traits\MutatesBoxId;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property User $user
 */
class Tenant extends Model
{
    use HasFactory, MutatesBoxId, Paginatable;

    protected $table = 'boxes';

    protected $primaryKey = 'box_id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $attributes = [
        'signup_use_contract_and_waivers' => 1,
        'max_bookings_per_athlete_per_day' => 1,
        'booking_threshold' => 7,
    ];

    protected $hidden = ['contract_terms_and_conditions'];

    protected $casts = [
        'signup_payment_options' => Serialize::class,
        'signup_debit_day_options' => Serialize::class,
        'extra_parameters' => Serialize::class,
        'sign_up_required_fields' => Serialize::class,
        'is_trial' => 'integer',
        'signup_use_contract_and_waivers' => 'integer',
        'limit_inter_facility_bookings' => 'integer',
        'box_status_id' => TenantStatus::class,
        'deactivated_on' => 'datetime',
        'deactivate_contracts_ended' => 'integer',
    ];

    /**
     * Mutate box_status_id to status
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_status_id,
            set: fn (mixed $value) => ['box_status_id' => $value]
        );
    }

    /**
     * Mutate box_desc to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_desc,
            set: fn (mixed $value) => ['box_desc' => $value]
        );
    }

    /**
     * Mutate box_billing_currency_id to tenant_billing_currency_id
     */
    protected function tenantBillingCurrencyId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_billing_currency_id,
            set: fn (mixed $value) => ['box_billing_currency_id' => $value]
        );
    }

    public function allUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_to_box', 'box_id', 'user_id', 'box_id', 'user_id')
            ->using(TenantUser::class)
            ->as('tenantUser')
            ->withPivot(
                'user_to_box_id',
                'effective_date',
                'end_date',
                'user_type_id',
                'user_status_id',
                'user_debit_status_id',
                'landing_screen',
                'high_risk',
            );
    }

    public function users(): BelongsToMany
    {
        return $this->allUsers()
            ->where('user_to_box.deleted', false)
            ->wherePivot('user_to_box.end_date', '>', today()->toDateString());
    }

    public function affiliations(): HasMany
    {
        return $this->hasMany(TenantAffiliation::class, 'tenant_id', 'box_id');
    }

    public function nonDeactivatedUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED)
            ->wherePivotNotIn('user_to_box.user_type_id', [UserType::LEAD_MEMBER, UserType::DISCOVERY]);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'box_id')
            ->where('box_facility.is_active', 1)
            ->orderBy('box_facility.box_facility_name');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notifications::class, 'box_id', 'box_id');
    }

    public function wods(): HasMany
    {
        return $this->hasMany(Wod::class, 'box_id', 'box_id')
            ->withoutGlobalScopes([OwnedByTenantScope::class]);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class, 'box_id', 'box_id');
    }

    public function headCoaches(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, TenantUser::class, 'box_id', 'user_id', 'box_id', 'user_id')
            ->where('user_to_box.user_type_id', UserType::HEAD_COACH->value);
    }

    public function firstHeadCoach(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'box_id')
            ->whereNotNull('created_on')
            ->orderBy('created_on')
            ->where('user_type_id', UserType::HEAD_COACH->value)
            ->limit(1);
    }

    public function crmSettings(): HasOne
    {
        return $this->hasOne(CrmSetting::class, 'box_id');
    }

    public function region(): HasOne
    {
        return $this->hasOne(Region::class, 'region_id', 'region_id');
    }

    public function leadSettings(): HasOne
    {
        return $this->hasOne(LeadSettings::class, 'box_id');
    }

    public function tenantCurrency(): HasOne
    {
        return $this->hasOne(Currency::class, 'currency_id', 'box_billing_currency_id');
    }

    public function memberCurrency(): HasOne
    {
        return $this->hasOne(Currency::class, 'currency_id', 'member_billing_currency_id');
    }

    public function timezone(): HasOne
    {
        return $this->hasOne(Timezone::class, 'timezone_id', 'timezone_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'box_id', 'box_id');
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class, 'box_id', 'box_id');
    }

    public function programmesActive(): HasMany
    {
        return $this->hasMany(Programme::class, 'box_id', 'box_id')->active();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id', 'country_id');
    }

    public function settings(): BelongsTo
    {
        return $this->belongsTo(Setting::class, 'box_id', 'box_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classes::class, 'box_id', 'box_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('boxes.box_status_id', '=', 1);
    }

    /**
     * Check if the box uses prorate strategy when onboarding.
     */
    public function usesProRateStrategy(): bool
    {
        return $this->pro_rate_strategy === 'automatic';
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::ACTIVE;
    }

    public function getConsistentBoxFacilityCategory(): ?LocationCategory
    {
        $category = null;

        /** @var Location $facility */
        foreach ($this->locations()->get() as $facility) {
            if ($category === null || $facility->category === $category) {
                $category = $facility->category;
            } else {
                $category = null;
            }
        }

        return $category;
    }
}
