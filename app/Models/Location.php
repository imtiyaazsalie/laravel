<?php

namespace App\Models;

use App\Casts\Serialize;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;
use Kirschbaum\PowerJoins\PowerJoins;
use Symfony\Component\Uid\Uuid;

class Location extends Model
{
    use HasFactory, IsOwnedByTenant, Paginatable, PowerJoins;

    protected $table = 'box_facility';

    protected $primaryKey = 'box_facility_id';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'extra_parameters' => Serialize::class,
        'attendance_code_expires_on' => 'datetime',
        'display_booking_details' => 'integer',
        'view_class_bookings' => 'integer',
        'can_debit' => 'integer',
        'visible_in_app_sessions' => 'integer',
        'deactivated_on' => 'datetime',
        'show_invoice_totals' => 'integer',
        'billing_payment_gateway_id' => \App\Enums\PaymentGateway::class,
    ];

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $hidden = [
        'extra_parameters',
    ];

    protected $attributes = [
        'is_active' => true,
        'max_bookings_per_athlete_per_day' => 1,
        'invoice_code_index' => 1,
    ];

    public function scopeGetUsersByFacilityId(Builder $query, $value)
    {
        return $query
            ->leftJoin('user_to_facility', 'box_facility.box_facility_id', '=', 'user_to_facility.box_facility_id')
            ->where('user_to_facility.box_facility_id', '=', $value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('box_facility.is_active', true);
    }

    public function activeMembersCount(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->users()
                    ->join('user_to_box', function ($query) {
                        $query->on('user_to_box.user_id', '=', 'user_to_facility.user_id')
                            ->where('user_to_box.box_id', '=', $this->tenant_id);
                    })
                    ->where('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED->value)
                    ->where('user_to_box.user_type_id', '=', UserType::GYM_MEMBER->value)
                    ->count();
            }
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(LocationUser::class, 'box_facility_id')
            ->where('user_to_facility.effective_date', '<=', today()->toDateString())
            ->where('user_to_facility.end_date', '>=', today()->toDateString());
    }

    public function leadWaivers(): HasMany
    {
        return $this->hasMany(LeadWaivers::class, 'box_facility_id');
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id', 'payment_gateway_id');
    }

    public function billingPaymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'billing_payment_gateway_id', 'payment_gateway_id');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Amenity::class,
            table: 'amenity_location',
            foreignPivotKey: 'location_id',
            relatedPivotKey: 'amenity_id',
            parentKey: 'box_facility_id',
            relatedKey: 'id',
            relation: null
        );
    }

    public function locationHealthProviders(): HasMany
    {
        return $this->hasMany(LocationHealthProvider::class, 'box_facility_id', 'box_facility_id');
    }

    public function healthProviders(): BelongsToMany
    {
        return $this->belongsToMany(HealthCareProvider::class, LocationHealthProvider::class, 'box_facility_id', 'health_provider_id', 'box_facility_id', 'id')
            ->using(LocationHealthProvider::class)
            ->as('locationHealthProvider');
    }

    public function debitBatches(): HasMany
    {
        return $this->hasMany(DebitBatch::class, 'box_facility_id', 'box_facility_id');
    }

    public function futureDebitBatches(): HasMany
    {
        return $this->hasMany(DebitBatch::class, 'box_facility_id', 'box_facility_id')
            ->joinRelationship('debitDayDate')
            ->where('debit_day_dates.debit_day_date', '>', today()->toDateString());
    }

    public function nextDebitBatch(): HasOne
    {
        return $this->hasOne(DebitBatch::class, 'box_facility_id')
            ->where('is_processed', 0);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classes::class, 'box_facility_id', 'box_facility_id')
            ->withoutGlobalScopes()
            ->where('classes.is_active', true);
    }

    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class, 'timezone_id', 'timezone_id');
    }

    public function crmSettings(): HasOne
    {
        return $this->hasOne(CrmSetting::class, 'box_facility_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LocationCategory::class, 'box_facility_category_id', 'box_facility_category_id');
    }

    public function financePaymentGateway(): BelongsTo
    {
        return $this->belongsTo(FinancePaymentGateway::class, 'box_facility_id', 'box_facility_id');
    }

    public function paymentGateways(): BelongsToMany
    {
        return $this->belongsToMany(PaymentGateway::class, LocationPaymentGateway::class, 'box_facility_id', 'payment_gateway_id');
    }

    public function locationPaymentGateways(): HasMany
    {
        return $this->hasMany(LocationPaymentGateway::class, 'box_facility_id', 'box_facility_id');
    }

    public function getInvoicePrefix(): string
    {
        return $this->prefix ?? 'IN';
    }

    public function generateInvoiceNumber(): string
    {
        $index = $this->invoice_code_index;

        $this->increment('invoice_code_index');

        return str($this->getInvoicePrefix())
            ->append(str_pad($index, 7, '0', STR_PAD_LEFT))
            ->toString();
    }

    /**
     * Mutate box_facility_name to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_facility_name,
            set: fn (mixed $value) => ['box_facility_name' => $value]
        );
    }

    /**
     * Mutate attendance_code
     */
    protected function attendanceCode(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => $value ? Uuid::fromBinary($value)->toRfc4122() : null,
            set: fn (mixed $value) => Uuid::fromString($value)->toBinary()
        );
    }

    /**
     * Mutate box_facility_category_id to category_id
     */
    protected function categoryId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_facility_category_id,
            set: fn (mixed $value) => ['box_facility_category_id' => $value]
        );
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logofile ? Storage::disk('public')->url($this->logofile) : null,
        );
    }

    /**
     * Mutate box_facility_prefix to prefix
     */
    protected function prefix(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_facility_prefix,
            set: fn (mixed $value) => ['box_facility_prefix' => $value]
        );
    }

    /**
     * Mutate box_facility_prefix to invoice_prefix
     */
    protected function invoicePrefix(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_facility_prefix,
            set: fn (mixed $value) => ['box_facility_prefix' => $value]
        );
    }

    /**
     * Mutate vat to vat_number
     */
    protected function vatNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->vat,
            set: fn (mixed $value) => ['vat' => $value]
        );
    }

    /**
     * Mutate vat_percent to vat_percentage
     */
    protected function vatPercentage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->vat_percent,
            set: fn (mixed $value) => ['vat_percent' => $value]
        );
    }

    /**
     * Mutate visible_in_app_sessions to is_visible_in_app_sessions
     */
    protected function IsVisibleInAppSessions(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->visible_in_app_sessions,
            set: fn (mixed $value) => ['visible_in_app_sessions' => $value]
        );
    }

    /**
     * Mutate invoiceinfo to invoice_information
     */
    protected function invoiceInformation(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->invoiceinfo,
            set: fn (mixed $value) => ['invoiceinfo' => $value]
        );
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class, 'location_id');
    }

    public function locationAmenities(): HasMany
    {
        return $this->hasMany(LocationAmenity::class, 'location_id');
    }

    public function addresses(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }
}
