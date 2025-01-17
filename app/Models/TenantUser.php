<?php

namespace App\Models;

use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Exceptions\LocationAccessException;
use App\Exceptions\TenantAccessException;
use App\Observers\TenantUserObserver;
use App\Services\FinanceService;
use App\Services\UserPackageService;
use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use App\Traits\SoftDeletesBoolean;
use Awobaz\Compoships\Compoships;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Kirschbaum\PowerJoins\PowerJoins;
use Reedware\LaravelCompositeRelations\CompositeHasMany;
use Reedware\LaravelCompositeRelations\HasCompositeRelations;

/**
 * @property int|string $user_id
 * @property int|string $tenant_id
 * @property int|string $programme_id
 * @property int|string $default_location_id
 * @property int|string $region_id
 * @property int|string $assigned_coach_id
 * @property UserType $type
 * @property UserStatus $status
 * @property UserDebitStatus $debit_status
 * @property string $member_id
 * @property string $bio
 * @property string $notes
 * @property string $landing_screen
 * @property int $auto_invoicing_day
 * @property int $auto_invoicing_due_day
 * @property bool $high_risk
 * @property Carbon $effective_date
 * @property Carbon $end_date
 * @property Carbon $go_cardless_link_sent_on
 * @property Carbon $stripe_bank_account_invitation_sent_on
 * @property Carbon $up_front_payment_end_date
 * @property Carbon $activated_on
 * @property Carbon $deactivated_on
 */
#[ObservedBy([TenantUserObserver::class])]
class TenantUser extends Model
{
    use Compoships, HasCompositeRelations, HasFactory, IsOwnedByTenant, Paginatable, PowerJoins, SoftDeletesBoolean;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $timestamps = true;

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'activated_on' => 'datetime',
        'deactivated_on' => 'datetime',
        'effective_date' => 'date',
        'end_date' => 'date',
        'user_status_id' => UserStatus::class,
        'user_debit_status_id' => UserDebitStatus::class,
        'user_type_id' => UserType::class,
        'up_front_payment_end_date' => 'date',
        'go_cardless_link_sent_on' => 'datetime',
        'payment_token_link_sent_on' => 'datetime',
    ];

    protected $table = 'user_to_box';

    protected $primaryKey = 'user_to_box_id';

    public $incrementing = true;

    public function defaultLocation(): HasOne
    {
        return $this->hasOne(Location::class, 'box_facility_id', 'default_box_facility_id');
    }

    public function userLocation(): HasMany
    {
        return $this->hasMany(LocationUser::class, 'user_id', 'user_id');
    }

    public function tenantUserLocation(): HasOne
    {
        return $this->hasOne(LocationUser::class, 'user_id', 'user_id')
            ->join('box_facility', 'user_to_facility.box_facility_id', '=', 'box_facility.box_facility_id')
            ->where('box_facility.box_id', '=', $this->box_id)
            ->whereDate('user_to_facility.effective_date', '<=', now())
            ->whereDate('user_to_facility.end_date', '>', now())
            ->orderBy('user_to_facility.user_to_facility_id', 'desc')
            ->limit(1)
            ->select('user_to_facility.*');
    }

    public function wodCaptures(): HasMany
    {
        return $this->hasMany(WodCapture::class, 'user_id', 'user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class, 'user_id', 'user_id');
    }

    public function bankAccount(): \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserBankingDetail::class, ['user_id', 'box_id'], ['user_id', 'box_id'])
            ->where('is_active', '=', true)
            ->latest();
    }

    public function userContract(): \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserContract::class, ['user_id', 'box_id'], ['user_id', 'box_id'])->latest('user_contract_id');
    }

    public function accessPrivileges(): \Awobaz\Compoships\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserAccessPrivilege::class, ['user_id', 'box_id'], ['user_id', 'box_id'])
            ->where('revoked', false);
    }

    public function locationAccessPrivileges(): HasMany
    {
        return $this->hasMany(LocationAccessPrivilege::class, 'user_id', 'user_id')
            ->where('revoked', false);
    }

    public function locations(): HasManyThrough
    {
        return $this->hasManyThrough(
            Location::class,
            LocationUser::class,
            'user_id',
            'box_facility_id',
            'user_id',
            'box_facility_id'
        )->where('box_facility.is_active', true)
            ->whereDate('user_to_facility.effective_date', '<=', now())
            ->whereDate('user_to_facility.end_date', '>', now())
            ->orderBy('box_facility.box_facility_name');
    }

    public function currentLocationUser(): HasOne
    {
        return $this->hasOne(LocationUser::class, 'user_id', 'user_id')
            ->whereDate('effective_date', '<=', now())
            ->whereDate('end_date', '>', now())
            ->latest('user_to_facility_id')
            ->limit(1);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function tenantNotifications(): HasManyThrough
    {
        return $this->hasManyThrough(Notifications::class, Tenant::class, 'box_id', 'box_id', 'box_id', 'box_id');
    }

    public function scopeFindTenantUserBy(Builder $query, $tenantId, $userId)
    {
        return $query
            ->where('box_id', '=', $tenantId)
            ->where('user_id', '=', $userId)
            ->withinActivePeriod();
    }

    public function scopeWithinActivePeriod(Builder $query): Builder
    {
        return $query->where('user_to_box.end_date', '>', today()->toDateString());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('user_to_box.end_date', '>', today()->toDateString())
            ->where('user_to_box.user_status_id', UserStatus::ACTIVE->value);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('user_to_box.end_date', '<=', today())
            ->where('user_to_box.user_status_id', '!=', UserStatus::ACTIVE->value);
    }

    public function scopeMemberships(Builder $query): Builder
    {
        return $query->where('user_to_box.user_type_id', '=', UserType::GYM_MEMBER->value);
    }

    public function scopeHeadCoaches(Builder $query): Builder
    {
        return $query->where('user_to_box.user_type_id', '=', UserType::HEAD_COACH->value);
    }

    public function scopeGymCoaches(Builder $query): Builder
    {
        return $query->where('user_to_box.user_type_id', '=', UserType::GYM_COACH->value);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('user_to_box.user_type_id', UserType::staffUserTypeIds());
    }

    public function scopeSearch(Builder $query, $search): Builder
    {
        return $query->join('users as searchUsers', 'user_to_box.user_id', '=', 'searchUsers.user_id')
            ->where(function ($query) use ($search) {
                $query->where(DB::raw("CONCAT(searchUsers.name, ' ', searchUsers.surname)"), 'LIKE', '%'.$search.'%')
                    ->orWhere('searchUsers.name', 'LIKE', '%'.$search.'%')
                    ->orWhere('searchUsers.surname', 'LIKE', '%'.$search.'%')
                    ->orWhere('searchUsers.email', 'LIKE', '%'.$search.'%');
            });
    }

    public function assignedCoach(): HasOne
    {
        return $this->hasOne(TenantUser::class, 'user_to_box_id', 'assigned_coach_utb_id');
    }

    public function isCoach(): bool
    {
        return in_array($this->user_type_id, [
            UserType::HEAD_COACH,
            UserType::GYM_COACH,
        ]);
    }

    public function isStaff(): bool
    {
        return in_array($this->user_type_id, [
            UserType::HEAD_COACH,
            UserType::GYM_COACH,
            UserType::BOX_ADMIN,
            UserType::BOX_FACILITY_ADMIN,
        ]);
    }

    public function isMember(): bool
    {
        return $this->user_type_id === UserType::GYM_MEMBER;
    }

    public function isLeadMember(): bool
    {
        return $this->user_type_id === UserType::LEAD_MEMBER;

    }

    public function isHeadCoach(): bool
    {
        return $this->user_type_id === UserType::HEAD_COACH;
    }

    public function isTenantAdmin(): bool
    {
        return $this->user_type_id === UserType::BOX_ADMIN;
    }

    public function isLocationAdmin(): bool
    {
        return $this->user_type_id === UserType::BOX_FACILITY_ADMIN;
    }

    public function isGymCoach(): bool
    {
        return $this->user_type_id === UserType::GYM_COACH;
    }

    public function isDebitOrder(): bool
    {
        return $this->user_debit_status_id === UserDebitStatus::DEBIT_ORDER;
    }

    public function isCash(): bool
    {
        return $this->user_debit_status_id === UserDebitStatus::CASH;
    }

    /**
     * Mutate user_status_id to status
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user_status_id,
            set: fn (mixed $value) => ['user_status_id' => $value]
        );
    }

    protected function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['box_id'],
            set: fn (mixed $value) => ['box_id' => $value]
        );
    }

    /**
     * Mutate user_type_id to type
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user_type_id,
            set: fn (mixed $value) => ['user_type_id' => $value]
        );
    }

    /**
     * Mutate user_debit_status_id to debit_status
     */
    protected function debitStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user_debit_status_id,
            set: fn (mixed $value) => ['user_debit_status_id' => $value]
        );
    }

    /**
     * Mutate default_box_facility_id to default_location_id
     */
    protected function defaultLocationId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->default_box_facility_id,
            set: fn (mixed $value) => ['default_box_facility_id' => $value]
        );
    }

    /**
     * Mutate assigned_coach_utb_id to assigned_coach_id
     */
    protected function assignedCoachId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->assigned_coach_utb_id,
            set: fn (mixed $value) => ['assigned_coach_utb_id' => $value]
        );
    }

    /**
     * Mutate effective_date to start_date
     */
    protected function startDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->effective_date,
            set: fn (mixed $value) => ['effective_date' => $value]
        );
    }

    public function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => (new FinanceService())->calculateMemberFee($this->user, $this->tenant)
        );
    }

    public function upfrontPaymentEndDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => Arr::has($attributes, 'up_front_payment_end_date') ? Carbon::parse(Arr::get($attributes, 'up_front_payment_end_date')) : null,
            set: fn (mixed $value) => ['up_front_payment_end_date' => $value]
        );
    }

    public function waiver(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user->getWaiver($this->tenant)
        );
    }

    public function activeUserPackages(): Attribute
    {
        return Attribute::make(
            get: fn () => (new UserPackageService)->getActiveUserPackagesForTenant($this->user, $this->tenant)
        );
    }

    /**
     * Abort if membership does not have access to a box.
     */
    public function ensureBoxAccess(string|int $boxId): void
    {
        if ($this->box_id !== $boxId) {
            throw new TenantAccessException;
        }
    }

    /**
     * Abort if membership does not have access to a box facility.
     *
     *
     * @throws LocationAccessException
     */
    public function ensureFacilityAccess(string|int $locationId): void
    {
        if (! $this->mayAccessFacility($locationId)) {
            throw new LocationAccessException;
        }
    }

    /**
     * Check if a membership has access to a box facility.
     */
    public function mayAccessFacility(string|int $locationId): bool
    {
        return LocationUser::query()
            ->active()
            ->whereUserId($this->user_id)
            ->where('user_to_facility.box_facility_id', '=', $locationId)
            ->exists();
    }

    public function isConnectedTo(TenantUser $tenantUser): bool
    {
        return $tenantUser->locations()
            ->active()
            ->whereIn(
                'user_to_facility.box_facility_id',
                $this->locations()->select('box_facility_id')->active()->get()->modelKeys()
            )->exists();
    }

    public function isConnectedToUser(string|int $userId): bool
    {
        $tenantUser = TenantUser::query()
            ->active()
            ->where('user_to_box.box_id', $this->box_id)
            ->where('user_to_box.user_id', $userId)
            ->first();

        if (! $tenantUser) {
            return false;
        }

        return $tenantUser->locations()
            ->active()
            ->whereIn(
                'user_to_facility.box_facility_id',
                $this->locations()->select('user_to_facility.box_facility_id')->active()->get()->modelKeys()
            )->exists();
    }

    /**
     * Check if the membership is a box super admin
     */
    public function getNumericAutoInvoicingDayForMonth(Carbon $date, string $invoiceDayString): ?string
    {
        if (is_numeric($invoiceDayString)) {
            return (string) $invoiceDayString;
        }

        if ($invoiceDayString === 'last_day_of_month') {
            return (string) $date->lastOfMonth()->format('d');
        }

        if ($invoiceDayString === 'second_last_day_of_month') {
            return (string) $date->lastOfMonth()->subDay()->format('d');
        }

        return null;
    }

    public function region(): \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function mandates(): CompositeHasMany
    {
        return $this->compositeHasMany(Mandate::class, ['box_id', 'user_id'], ['box_id', 'user_id']);
    }

    public function leadMember()
    {
        return $this->hasOne(LeadMember::class, 'member_id', 'lead_member_id');
    }
}
