<?php

namespace App\Models;

use App\Enums\ClassType;
use App\Enums\CoachType;
use App\Enums\DayOfWeek;
use App\Services\ClassDateService;
use App\Traits\HasTags;
use App\Traits\MutatesBoxFacilityId;
use App\Traits\Paginatable;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Kirschbaum\PowerJoins\PowerJoins;

class Classes extends Model
{
    use HasFactory, HasTags, MutatesBoxFacilityId, Paginatable, PowerJoins;

    protected $table = 'classes';

    protected $primaryKey = 'class_id';

    protected $guarded = [];

    protected $hidden = [
        'meeting_url',
    ];

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_virtual' => 'integer',
        'is_session' => 'integer',
        'is_display_coach_name' => 'integer',
        'is_visible_in_app' => 'integer',
        'is_active' => 'integer',
        'recurring_end_date' => 'date',
        'class_type_id' => ClassType::class,
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'description' => '',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->capturer_id = Auth::id();
        });

        static::updating(function ($model) {
            $model->updated_by_id = Auth::id();
        });
    }

    /**
     * Mutate class_name to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->class_name,
            set: fn (mixed $value) => ['class_name' => $value]
        );
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => ['description' => $value ?? '']
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
     * Mutate class_limit to limit
     */
    protected function limit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->class_limit,
            set: fn (mixed $value) => ['class_limit' => $value]
        );
    }

    /**
     * Mutate class_type_id to type
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->class_type_id,
            set: fn (mixed $value) => ['class_type_id' => $value]
        );
    }

    /**
     * Mutate cancellation_threshhold to cancellation_threshold (Spelling)
     */
    protected function cancellationThreshold(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cancellation_threshhold,
            set: fn (mixed $value) => ['cancellation_threshhold' => $value]
        );
    }

    protected function instructorId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->headCoach?->user_id,
            set: fn (mixed $value) => ['coach_id' => $value]
        );
    }

    protected function supportingInstructorId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->supportingCoach?->user_id,
            set: fn (mixed $value) => ['supporting_coach_id' => $value]
        );
    }

    public function location(): HasOne
    {
        return $this->hasOne(Location::class, 'box_facility_id', 'box_facility_id');
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'box_id', 'box_id');
    }

    public function createdBy(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'capturer_id');
    }

    public function updatedBy(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'updated_by_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class, 'class_id', 'class_id');
    }

    public function recurringBookings(): HasMany
    {
        return $this->hasMany(ClassRecurringBooking::class, 'class_id', 'class_id')
            ->where('class_recurring_bookings.active', true);
    }

    public function coaches(): HasMany
    {
        return $this->hasMany(ClassCoach::class, 'class_id');
    }

    public function headCoach(): HasOne
    {
        return $this->hasOne(ClassCoach::class, 'class_id')
            ->where('coach_type_id', '=', CoachType::HEAD_COACH)
            ->where('is_active', '=', true)
            ->latest();
    }

    public function supportingCoach(): HasOne
    {
        return $this->hasOne(ClassCoach::class, 'class_id')
            ->where('coach_type_id', '=', CoachType::SUPPORTING_COACH)
            ->where('is_active', '=', true)
            ->latest();
    }

    public function classPackages(): HasMany
    {
        return $this->hasMany(ClassPackage::class, 'class_id', 'class_id');
    }

    public function classDates(): HasMany
    {
        return $this->hasMany(ClassDate::class, 'class_id', 'class_id');
    }

    public function futureClassDates(): HasMany
    {
        return $this->hasMany(ClassDate::class, 'class_id', 'class_id')->whereDate('class_date', '>=', today());
    }

    public function scopeHasDatesBetween(Builder $query, string|Carbon $start, string|Carbon $end): Builder
    {
        if (is_string($start)) {
            $start = Carbon::parse($start);
        }

        if (is_string($end)) {
            $end = Carbon::parse($end);
        }

        return $query->whereHas('classDates', function ($query) use ($start, $end) {
            $query->between($start, $end);
        });
    }

    public function scopeHasDatesFrom(Builder $query, string|Carbon $date): Builder
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $query->joinRelationship('classDates')
            ->where('class_date', '>=', $date->toDateString());

        // return $query->whereHas('classDates', function($query) use ($date) {
        //     $query->where('class_date', '>=', $date->toDateString());
        // });
    }

    public function scopeHasDatesTo(Builder $query, string|Carbon $date): Builder
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $query->joinRelationship('classDates')
            ->where('class_date', '<', $date->toDateString());
    }

    public function scopeHasBookingsFor(Builder $query, string|int $userId): Builder
    {
        return $query->joinRelationship('bookings')
            ->where('user_id', $userId);
    }

    public function scopeOnceOff(Builder $query): Builder
    {
        return $query->where('class_type_id', ClassType::ONCE_OFF->value);
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('class_type_id', ClassType::RECURRING->value);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isVirtual(): bool
    {
        return (bool) $this->is_virtual;
    }

    public function isVisibleInApp(): bool
    {
        return (bool) $this->is_visible_in_app;
    }

    public function isDisplayCoachName(): bool
    {
        return (bool) $this->is_display_coach_name;
    }

    public function isNotActive(): bool
    {
        return ! $this->isActive();
    }

    public function isSession(): bool
    {
        return (bool) $this->is_session;
    }

    public function isNotSession(): bool
    {
        return ! $this->isSession();
    }

    public function isFree(): bool
    {
        return (bool) $this->is_free;
    }

    public function isNotFree(): bool
    {
        return ! $this->isFree();
    }

    public function isOnceOff(): bool
    {
        return $this->class_type_id === ClassType::ONCE_OFF;
    }

    public function isRecurring(): bool
    {
        return $this->class_type_id === ClassType::RECURRING;
    }

    public function timezone()
    {
        return $this->location->timezone ?? $this->tenant->timezone;
    }

    public function getTimeString(): string
    {
        return str($this->start_time)
            ->append(' - ')
            ->append($this->end_time)
            ->toString();
    }

    public function bookingThreshold(): int
    {
        return $this->booking_threshold;
    }

    public function daysOfWeek(): HasMany
    {
        return $this->hasMany(ClassToDay::class, 'class_id', 'class_id');
    }

    public function ensureClassDates(?Carbon $startDate = null): void
    {
        $inserts = [];
        $fromDate = $startDate ?? today();
        $endDate = today()->addMonthsNoOverflow(3);

        if ($this->recurring_end_date && $this->recurring_end_date->lessThan(today()->addMonthsNoOverflow(3))) {
            $endDate = $this->recurring_end_date;
        }

        if ($endDate->lessThan($fromDate)) {
            return;
        }

        $daysOfWeek = $this->getActiveClassDays()->pluck('day_id')->toArray();

        $dates = (new CarbonPeriod($fromDate, $endDate))->filter(function ($date) use ($daysOfWeek) {
            return in_array($date->dayOfWeekIso, $daysOfWeek);
        })->toArray();

        foreach ($dates as $date) {
            // check if class date already exists
            if ((new ClassDateService())->getClassDateForClassAndDate($this->getKey(), $date)) {
                continue;
            }

            $inserts[] = [
                'class_id' => $this->getKey(),
                'class_date' => $date,
            ];
        }

        if (! empty($inserts)) {
            $this->classDates()->createMany($inserts);
        }
    }

    public function getActiveClassDays(bool $asString = false)
    {
        if ($asString) {
            $classDays = null;

            /** @var ClassDay $classDay */
            foreach ($this->daysOfWeek as $classDay) {
                $day = date('D', strtotime(DayOfWeek::tryFrom($classDay->day_id)->name));
                $classDays .= "$day, ";
            }

            return rtrim($classDays, ', ');
        }

        return $this->daysOfWeek->filter(function ($classDay) {
            return $classDay->is_active;
        });
    }
}
