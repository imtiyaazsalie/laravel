<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\InjuryStatus;
use App\Enums\UserStatus;
use App\Notifications\ResetPassword;
use App\Observers\UserObserver;
use App\Services\UserPackageService;
use App\Traits\Paginatable;
use App\Traits\SoftDeletesBoolean;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Timebox;
use Kirschbaum\PowerJoins\PowerJoins;
use Laravel\Passport\HasApiTokens;
use RuntimeException;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Paginatable, PowerJoins, SoftDeletesBoolean;

    const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    const ROLE_ADMIN = 'ROLE_ADMIN';

    const ROLE_USER = 'ROLE_USER';

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $table = 'users';

    public $timestamps = true;

    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'activated_at' => 'datetime',
        'joined_at' => 'datetime',
        'dob' => 'date',
        'is_redacted' => 'integer',
        'gender_id' => Gender::class,
        'user_status_id' => UserStatus::class,
    ];

    protected $attributes = [
        'user_status_id' => 2,
        'locked' => 0,
        'high_risk' => 0,
        'terms_and_conditions_accepted' => 0,
        'measure_preference' => 0,
        'deleted' => 0,
    ];

    protected $primaryKey = 'user_id';

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * Accessor for Age.
     */
    public function age()
    {
        return $this->getAttribute('dob') ? Carbon::parse($this->getAttribute('dob'))?->age : null;
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->where('users.user_id', $value)->firstOrFail();
    }

    public function scopeUserTenantByTenant(Builder $query, $tenantId): Builder
    {
        return $query->joinRelationship('tenantUser')->where('user_to_box.box_id', '=', $tenantId);
    }

    public function scopeActiveAfter(Builder $query, $date): Builder
    {
        return $query->joinRelationship('tenantUser')->where('user_to_box.effective_date', '>=', $date);
    }

    public function scopeEndsAfter(Builder $query, $date): Builder
    {
        return $query->joinRelationship('tenantUser')->where('user_to_box.end_date', '>', $date);
    }

    public function scopeBirthdayBetween(Builder $query, string $start, string $end): Builder
    {
        $start = Carbon::parse($start)->setYear(now()->year)->toDateString();
        $end = Carbon::parse($end)->setYear(now()->year)->toDateString();

        return $query->whereRaw("CONCAT('".now()->year."', '-', DATE_FORMAT(users.dob, '%m-%d')) BETWEEN '{$start}' AND '{$end}'");
    }

    public function routeNotificationForExpo($notification)
    {
        return [$this->push_notification_token];
    }

    public function healthProvider(): HasOne
    {
        return $this->hasOne(HealthCareProvider::class, 'id', 'health_provider_id');
    }

    public function crmRecipient(): MorphOne
    {
        return $this->morphOne(MailerRecipient::class, 'crmRecipient');
    }

    public function injuries(): HasMany
    {
        return $this->hasMany(Injury::class, 'created_for_id');
    }

    public function isInjured(): ?bool
    {
        return $this->injuries()->where('status', '=', InjuryStatus::INJURED)->exists();
    }

    public function locations(): BelongsToMany
    {
        return $this->allLocations()
            ->where('user_to_facility.end_date', '>', today()->toDateString())
            ->where('user_to_facility.effective_date', '<=', today()->toDateString());
    }

    public function allLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, LocationUser::class, 'user_id', 'box_facility_id', 'user_id', 'box_facility_id')
            ->using(LocationUser::class)
            ->as('locationUser')
            ->withPivot('effective_date', 'end_date');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, UserPackage::class, 'user_id', 'package_id', 'user_id', 'package_id')
            ->using(UserPackage::class)
            ->as('userPackage')
            ->withPivot(
                'effective_date',
                'end_date',
                'sessions_available',
                'sessions_expire',
                'notes'
            )
            ->where(function ($query) {
                $query->whereNull('user_to_package.end_date')->orWhere('user_to_package.end_date', '>', today()->toDateString());
            })->where('packages.is_active', true);
    }

    // public function validateForPassportPasswordGrant($password)
    // {
    //     return Hash::check($password, $this->password);
    // }

    public function allPackages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, UserPackage::class, 'user_id', 'package_id', 'user_id', 'package_id')
            ->using(UserPackage::class)
            ->as('userPackage')
            ->withPivot(
                'effective_date',
                'end_date',
                'sessions_available',
                'sessions_expire',
                'notes'
            );
    }

    public function userLocations(): HasMany
    {
        return $this->hasMany(LocationUser::class, 'user_id');
    }

    public function coronavirusVaccinationDetails(): HasOne
    {
        return $this->hasOne(CoronavirusVaccinationDetails::class, 'user_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'user_id');
    }

    public function scopeFacilityUsers(): LocationUser
    {
        return LocationUser::whereIn('user_to_facility.box_facility_id', $this->scopeFacilities()->pluck('box_facility_id'));
    }

    public function scopeFacilities()
    {
        return Location::leftJoin('boxes', 'boxes.box_id', 'box_facility.box_id')
            ->whereIn('box_facility.box_id', $this->scopeBoxes()->pluck('boxes.box_id'));
    }

    public function scopeBoxes()
    {
        return Tenant::leftJoin('user_to_box', 'user_to_box.box_id', 'boxes.box_id')
            ->where('user_id', '=', $this->getAuthIdentifier());
    }

    public function getFullNameAttribute(): string
    {
        return $this->name.' '.$this->surname;
    }

    public function location(): HasManyThrough
    {
        return $this->hasManyThrough(LocationUser::class, User::class, 'user_id', 'user_id');
    }

    public function scopeHasRoles($query, array|string $roles, $userId = null, $boxId = null)
    {
        $userId = is_null($userId) ? auth()->user()->getAuthIdentifier() : $userId;

        if (gettype($roles) == 'string') {
            $roles = explode(',', $roles);
        }

        return $query->leftJoin('user_to_box', 'users.user_id', 'user_to_box.user_id')
            ->leftJoin('user_types', 'user_to_box.user_type_id', 'user_types.user_type_id')
            ->whereIn('user_types.user_type_desc', $roles)
            ->where('users.user_id', '=', $userId)
            ->when(! is_null($boxId), function ($query) use ($boxId) {
                return $query->where('user_to_box.box_id', '=', $boxId);
            })
            ->exists();
    }

    public function scopeUserLocations($query, array|string $roles, $userId = null, $boxId = null)
    {
        $userId = is_null($userId) ? auth()->user()->getAuthIdentifier() : $userId;

        if (gettype($roles) == 'string') {
            $roles = explode(',', $roles);
        }

        return $query->leftJoin('user_to_box', 'users.user_id', 'user_to_box.user_id')
            ->leftJoin('user_types', 'user_to_box.user_type_id', 'user_types.user_type_id')
            ->whereIn('user_types.user_type_desc', $roles)
            ->where('users.user_id', '=', $userId)
            ->when(! is_null($boxId), function ($query) use ($boxId) {
                return $query->where('user_to_box.box_id', '=', $boxId);
            })
            ->exists();
    }

    public function scopeSearch(Builder $query, $searchTerm)
    {
        return $query->where(DB::raw("CONCAT(users.name, ' ', users.surname)"), 'LIKE', '%'.$searchTerm.'%')
            ->orWhere('name', 'LIKE', '%'.$searchTerm.'%')
            ->orWhere('surname', 'LIKE', '%'.$searchTerm.'%')
            ->orWhere('email', 'LIKE', '%'.$searchTerm.'%')
            ->orWhere('users.user_id', '=', $searchTerm);
    }

    public function tenantUser(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'user_id', 'user_id');
    }

    public function userTenant(): HasOne
    {
        return $this->hasOne(TenantUser::class, 'user_id', 'user_id');
    }

    public function programme(): HasOneThrough
    {
        return $this->hasOneThrough(Programme::class, TenantUser::class, 'user_id', 'id', 'user_id', 'programme_id');
    }

    public function coach(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, TenantUser::class, 'user_id', 'user_id', 'user_id', 'assigned_coach_utb_id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->allTenants()
            ->wherePivot('user_to_box.end_date', '>', today());
    }

    public function allTenants(): BelongsToMany
    {
        return $this->belongsToMany(
            Tenant::class,
            TenantUser::class,
            'user_id', 'box_id', 'user_id', 'box_id')
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
            )->withTimestamps();
    }

    /**
     * Get the tenantUser pivot data for the current tenant.
     */
    public function currentTenant(): Tenant
    {
        if (Tenant::checkCurrent()) {
            return $this->allTenants()->first();
        }

        throw new RuntimeException('currentTenant() method should only be used when tenant is set.');
    }

    public function isTenantSuperAdmin(): bool
    {
        return $this->hasRole(config('octiv.gym_super_admin_role'));
    }

    public function isDiscoveryUser(): bool
    {
        return (bool) $this->is_redacted;
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(UserContract::class, 'user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class, 'user_id');
    }

    public function latestBooking(): HasOne
    {
        return $this->hasOne(ClassBooking::class, 'user_id')
            ->booked()
            ->orWhere(function ($query) {
                $query->checkedIn();
            })
            ->latest('class_bookings.dt_added');
    }

    public function wodCaptures(): HasMany
    {
        return $this->hasMany(WodCapture::class, 'user_id');
    }

    public function userOnHold(): HasMany
    {
        return $this->hasMany(UserOnHold::class, 'user_id');
    }

    public function coronovirusVaccinationDetails(): HasOne
    {
        return $this->hasOne(CoronavirusVaccinationDetails::class, 'user_id');
    }

    public function scheduledActions(): HasMany
    {
        return $this->hasMany(ScheduleUserAction::class, 'user_id');
    }

    public function bankAccount(): HasOne
    {
        return $this->hasOne(UserBankingDetail::class, 'user_id');
    }

    public function goCardlessMandatesDebitOrder(): HasOne
    {
        return $this->hasOne(MandateGoCardless::class, 'user_id')
            ->whereHas('locationPaymentGateway', function ($query) {
                $query->where('context', '=', 'debit_order');
            });
    }

    public function latestContract(?Tenant $tenant = null): HasOne
    {
        return $this->hasOne(UserContract::class, 'user_id')
            ->when($tenant instanceof Tenant, function ($query) use ($tenant) {
                $query->where('box_id', $tenant->getKey());
            })
            ->latest('ending_on');
    }

    public function isAdmin(): bool
    {
        return $this->user_type_id == 1;
    }

    public function receivesPushNotifications(): bool
    {
        return ! is_null($this->pushNotificationToken());
    }

    public function pushNotificationToken(): ?string
    {
        return $this->push_notification_token;
    }

    public function hasPackageWithProgrammeVisibility(array|string|int $ids): bool
    {
        $ids = (array) $ids;

        if (empty($ids)) {
            return false;
        }

        if (count($ids) === 1) {
            return $this->userPackages()->where(function ($query) use ($ids) {
                $query->whereDoesntHave('programmeVisibilities')
                    ->orWhereHas('programmeVisibilities', function ($query) use ($ids) {
                        $query->whereIn('programme_package_visibilities.programme_id', $ids);
                    });
            })->exists();
        }

        $packages = $this->userPackages()->with('programmeVisibilities')->get();

        //packages without programme visibilities have access to all programmes.
        if ($packages->first(fn ($p) => $p->programmeVisibilities->isEmpty())) {
            return true;
        }

        //limit packages needs to be checked against each programme ID.
        foreach ($ids as $id) {
            if ($packages->first(
                fn ($p) => $p->programmeVisibilities->where('programme_id', $id)->first()
            )) {
                continue;
            }

            return false;
        }

        return true;
    }

    public function isUnsubscribedFromNotification(Notifications $notification): bool
    {
        return NotificationUnsubscriptions::query()
            ->where('user_id', $this->getAuthIdentifier())
            ->where(function ($query) use ($notification) {
                $query->where('notification_id', $notification->getKey())
                    ->orWhere('notification_id', $notification->parent_id);
            })->exists();
    }

    public function userPackages(): HasMany
    {
        return $this->hasMany(UserPackage::class, 'user_id');
    }

    public function waivers(): HasMany
    {
        return $this->hasMany(LeadWaivers::class, 'user_id');
    }

    public function scopeBirthdaysBetween(Builder $query, string $startDate, string $endDate): Builder
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $startMonth = $startDate->month;
        $startDay = $startDate->day;
        $endMonth = $endDate->month;
        $endDay = $endDate->day;

        if ($startMonth > $endMonth || ($startMonth === $endMonth && $startDay > $endDay)) {
            return $query->where(function ($query) use ($startMonth, $startDay, $endMonth, $endDay) {
                $query->whereMonth('dob', '>=', $startMonth)
                    ->whereDay('dob', '>=', $startDay)
                    ->orWhere(function ($query) use ($endMonth, $endDay) {
                        $query->whereMonth('dob', '<=', $endMonth)
                            ->whereDay('dob', '<=', $endDay);
                    });
            });
        }

        return $query->where(function ($query) use ($startMonth, $startDay, $endMonth, $endDay) {
            $query->whereMonth('dob', '>=', $startMonth)
                ->whereDay('dob', '>=', $startDay)
                ->whereMonth('dob', '<=', $endMonth)
                ->whereDay('dob', '<=', $endDay);
        });
    }

    public function hasAcceptedTermsAndConditions(): bool
    {
        return ! is_null($this->terms_and_conditions_accepted_on);
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user_status_id,
            set: fn (mixed $value) => ['user_status_id' => $value]
        );
    }

    /**
     * Mutate gender_id to gender
     */
    protected function gender(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->gender_id,
            set: fn (mixed $value) => ['gender_id' => $value]
        );
    }

    /**
     * Mutate profilepic to image
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profilepic,
            set: fn (mixed $value) => ['profilepic' => $value]
        );
    }

    //TODO: scope to sessions remaining

    /**
     * Mutate profilepic to image_url
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profilepic
                ? Storage::disk('public')->url($this->profilepic)
                : null,
        );
    }

    protected function dateOfBirth(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->dob,
            set: fn (mixed $value) => ['dob' => $value]
        );
    }

    public function getActivePackagesDateStringForTenant(Tenant|int $tenant): string
    {
        $str = '';

        /** @var UserPackage $userPackage */
        foreach ((new UserPackageService())->getActiveUserPackagesForTenant($this, $tenant) as $userPackage) {
            $endDate = $userPackage->end_date ? $userPackage->end_date->format('Y-m-d') : 'Open';

            $str .= $userPackage->effective_date->format('Y-m-d').' / '.$endDate.PHP_EOL;
        }

        return trim($str, PHP_EOL);
    }

    public function getActivePackagesStringForTenant(Tenant|int $tenant): string
    {
        $str = '';

        /** @var UserPackage $userPackage */
        foreach ((new UserPackageService())->getActiveUserPackagesForTenant($this, $tenant) as $userPackage) {
            $str .= $userPackage->package->name.' ('.$userPackage->sessionsAvailableAsText().')'.PHP_EOL;
        }

        return trim($str, PHP_EOL);
    }

    public function getWaiver(Tenant $tenant): ?LeadWaivers
    {
        return $this->waivers()
            ->with('location.tenant')
            ->whereRelation('location', 'box_id', '=', $tenant->getKey())
            ->latest()
            ->first();
    }

    public function findAndValidateForPassport($username, $password): ?User
    {
        $userCheck = (new Timebox)->call(function (Timebox $timebox) use ($username, $password) {
            return User::where('email', '=', $username)
                ->where('password', '=', md5($password))
                ->first();
        }, 200);

        if ($userCheck) {
            $userCheck->update([
                'password' => Hash::make($password),
            ]);

            return $userCheck;
        }

        $user = User::where('email', '=', $username)
            ->first();

        if ($user && Hash::check($password, $user->password)) {
            return $user;
        }

        return null;

    }

    public function scopeWithHasSessionRemainingForDate(Builder $query, Tenant $tenant, Carbon $date, ?Location $location, array $packageIds = []): Builder
    {
        //TODO: Filter out by package IDS..?

        $bookingsForDayQuery = <<<'HEREA'
            (
                SELECT
                    count(class_bookings.class_booking_id)
                FROM
                    class_bookings
                LEFT JOIN class_to_dates ON class_bookings.class_to_date_id = class_to_dates.class_to_date_id
                LEFT JOIN classes ON class_to_dates.class_id = classes.class_id
                INNER JOIN box_facility ON box_facility.box_facility_id = classes.box_facility_id
             WHERE
                class_bookings.user_id = users.user_id
                AND class_bookings.class_booking_status_id NOT IN(2, 4)
                AND class_to_dates.is_active = 1
                AND classes.is_free = 0
                AND class_to_dates.class_date = '%s'
                AND box_facility.box_id = '%s'
            )
            HEREA;

        //check interfactiliy bookings
        if ($limitInterfacilityBookings = $tenant->limit_inter_facility_bookings && $location) {
            $bookingsForDayQuery = str($bookingsForDayQuery)
                ->beforeLast(')')
                ->append(' AND box_facility.box_facility_id = '.$location->getKey().' )');
        }

        $subQuery = <<<'HEREA'
            (
                SELECT
                    count(class_bookings.class_booking_id)
                FROM
                    class_bookings
                LEFT JOIN class_to_dates ON class_bookings.class_to_date_id = class_to_dates.class_to_date_id
                LEFT JOIN classes ON class_to_dates.class_id = classes.class_id
                INNER JOIN box_facility ON box_facility.box_facility_id = classes.box_facility_id
             WHERE
                class_bookings.user_id = users.user_id
                AND class_bookings.class_booking_status_id NOT IN(2, 4)
                AND class_to_dates.is_active = 1
                AND class_bookings.top_up_used = 0
                AND classes.is_free = 0
                AND class_to_dates.class_date BETWEEN '%s' AND '%s'
                AND box_facility.box_id = '%s'
            )
            HEREA;

        //check interfactiliy bookings
        if ($limitInterfacilityBookings = $tenant->limit_inter_facility_bookings && $location) {
            $subQuery = str($subQuery)
                ->beforeLast(')')
                ->append(" AND box_facility.box_facility_id = '%s' )");
        }

        $hasActivePackageQueryWithLimitOfZero = <<<'HEREA'
            (SELECT COUNT(packages.package_id) FROM user_to_package
                INNER JOIN packages on user_to_package.package_id = packages.package_id AND packages.box_id = %s AND packages.is_active = 1
                WHERE user_to_package.deleted = 0
                    AND user_to_package.effective_date <= '%s'
                    AND (user_to_package.end_date IS NULL OR user_to_package.end_date > '%s')
                    AND packages.package_limit = 0
                    AND user_to_package.user_id = users.user_id
                LIMIT 1)
            HEREA;

        $hasActivePackagesWithSessionsAvailable = <<<'HEREA'
            (SELECT
                CASE WHEN packages.package_limit_type_id = 3 THEN
                    (CASE WHEN user_to_package.sessions_available > 0 THEN 1 ELSE 0 END)
                WHEN packages.package_limit_type_id = 2 THEN
                    (CASE WHEN ((packages.package_limit + CASE WHEN user_to_package.sessions_available IS NULL THEN 0 ELSE user_to_package.sessions_available END) - %s) > 0 THEN 1 ELSE 0 END)
                WHEN packages.package_limit_type_id = 1 THEN
                    (CASE WHEN ((packages.package_limit + CASE WHEN user_to_package.sessions_available IS NULL THEN 0 ELSE user_to_package.sessions_available END) - %s) > 0 THEN 1 ELSE 0 END)
                ELSE
                    0
                END AS has_sessions_available_for_date
                FROM user_to_package
                INNER JOIN packages on user_to_package.package_id = packages.package_id AND packages.box_id = %s AND packages.is_active = 1
                WHERE user_to_package.deleted = 0
                    AND user_to_package.effective_date <= '%s'
                    AND (user_to_package.end_date IS NULL OR user_to_package.end_date > '%s')
                    AND user_to_package.user_id = users.user_id
                ORDER BY has_sessions_available_for_date DESC
                LIMIT 1)
        HEREA;

        $select = <<<'HEREA'
            CASE
                WHEN (%s > 0) THEN
                    1
                WHEN (%s = 1) THEN
                    1
                ELSE
                    0
                END
            AS has_sessions_available_for_date
        HEREA;

        if ($location?->max_bookings_per_athlete_per_day) {
            $select = str($select)
                ->after('CASE')
                ->prepend('CASE WHEN (%s >= '.$location->max_bookings_per_athlete_per_day.') THEN 0 ')
                ->replaceFirst('%s', sprintf($bookingsForDayQuery, $date->toDateString(), $tenant->getKey()));
        }

        $select = sprintf(
            $select,

            sprintf(
                $hasActivePackageQueryWithLimitOfZero,
                $tenant->getKey(),
                $date->toDateString(),
                $date->toDateString()
            ),

            sprintf(
                $hasActivePackagesWithSessionsAvailable,

                $limitInterfacilityBookings
                    ? sprintf($subQuery, $date->clone()->startOfWeek()->toDateString(), $date->clone()->endOfWeek()->toDateString(), $tenant->getKey(), $location->getKey())
                    : sprintf($subQuery, $date->clone()->startOfWeek()->toDateString(), $date->clone()->endOfWeek()->toDateString(), $tenant->getKey()),

                $limitInterfacilityBookings
                    ? sprintf($subQuery, $date->clone()->startOfMonth()->toDateString(), $date->clone()->endOfMonth()->toDateString(), $tenant->getKey(), $location->getKey())
                    : sprintf($subQuery, $date->clone()->startOfMonth()->toDateString(), $date->clone()->endOfMonth()->toDateString(), $tenant->getKey()),

                $tenant->getKey(),
                $date->toDateString(),
                $date->toDateString()
            ),
        );

        return $query->select('users.*')
            ->addSelect(DB::raw(str_replace(PHP_EOL, '', $select)));
    }
}
