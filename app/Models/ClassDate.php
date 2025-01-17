<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use App\Traits\HasTags;
use App\Traits\Paginatable;
use App\Traits\RecordUserOnCreateAndUpdate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Kirschbaum\PowerJoins\PowerJoins;

class ClassDate extends Model
{
    use HasTags, Paginatable, PowerJoins, RecordUserOnCreateAndUpdate;

    protected $table = 'class_to_dates';

    protected $primaryKey = 'class_to_date_id';

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    public const CREATED_BY_ID = null;

    protected $guarded = [];

    protected $casts = [
        'class_date' => 'date',
        'class_limit' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_active' => 'integer',
    ];

    protected $attributes = [
        'is_active' => 1,
    ];

    /**
     * Mutate class_date to date
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->class_date,
            set: fn (mixed $value) => ['class_date' => $value]
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

    public function classBookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class, 'class_to_date_id');
    }

    public function classBookingWaitingList(): HasMany
    {
        return $this->hasMany(ClassBookingWaitingList::class, 'class_to_date_id');
    }

    protected function instructorId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->coach_id,
            set: fn (mixed $value) => ['coach_id' => $value]
        );
    }

    protected function supportingInstructorId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->supporting_coach_id,
            set: fn (mixed $value) => ['supporting_coach_id' => $value]
        );
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function headCoach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function supportingCoach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supporting_coach_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeInFuture(Builder $query): Builder
    {
        return $query->whereDate('class_date', '>', now());
    }

    public function scopeBetween(Builder $query, $start, $end): Builder
    {
        if (is_string($start)) {
            $start = Carbon::parse($start);
        }

        if (is_string($end)) {
            $end = Carbon::parse($end);
        }

        return $query->where(function ($query) use ($start, $end) {
            $query->where('class_date', '>=', $start->toDateString())
                ->where('class_date', '<=', $end->toDateString());
        });
    }

    public function scopeWithCoachIds(Builder $query): Builder
    {
        return $query
            ->join('class_coaches as cc', function ($join) {
                $join->on('cc.class_id', '=', 'c.class_id')
                    ->on('cc.coach_id', '=', DB::raw('(
                        SELECT class_coaches.coach_id FROM class_coaches
                            WHERE class_coaches.class_id = class_to_dates.class_id
                            AND `class_coaches`.`coach_type_id` = 1
                            AND `class_to_dates`.`class_date` >= DATE(class_coaches.dt_added)
                            and (
                                `class_coaches`.`is_active` = 1
                                or (
                                  `class_coaches`.`is_active` = 0 and class_coaches.dt_modified >= `class_to_dates`.`class_date`
                                )
                            )
                        ORDER BY class_coaches.dt_added DESC LIMIT 1
                      )'));
            })
            ->leftJoin('class_coaches as cc2', function ($join) {
                $join->on('cc2.class_id', '=', 'c.class_id')
                    ->on('cc2.coach_id', '=', DB::raw('(
                        SELECT class_coaches.coach_id FROM class_coaches
                            WHERE class_coaches.class_id = class_to_dates.class_id
                            AND `class_coaches`.`coach_type_id` = 2
                            AND `class_to_dates`.`class_date` >= DATE(class_coaches.dt_added)
                            and (
                                `class_coaches`.`is_active` = 1
                                or (
                                `class_coaches`.`is_active` = 0 and class_coaches.dt_modified >= `class_to_dates`.`class_date`
                                )
                            )
                        ORDER BY class_coaches.dt_added DESC LIMIT 1
                    )'));
            })
            ->leftJoin('users as uctd', 'class_to_dates.coach_id', '=', 'uctd.user_id')
            ->leftJoin('users as uctd2', 'class_to_dates.supporting_coach_id', '=', 'uctd2.user_id')
            ->join('users as ucc', 'cc.coach_id', '=', 'ucc.user_id')
            ->leftJoin('users as ucc2', 'cc2.coach_id', '=', 'ucc2.user_id')
            ->select(
                'class_to_dates.class_to_date_id',
                'class_to_dates.class_id',
                'class_to_dates.class_date',
                'class_to_dates.dt_added',
                'class_to_dates.dt_modified',
                'class_to_dates.is_active',
                'class_to_dates.start_time',
                'class_to_dates.end_time',
                'class_to_dates.class_limit',
                'class_to_dates.updated_by_id',
                'class_to_dates.meeting_url',
                'class_to_dates.name',
                'class_to_dates.description',
                'class_to_dates.min_booked_members_count',
                'class_to_dates.auto_cancel_threshold_min',
            )
            ->addSelect(DB::raw('IF (uctd.user_id IS NULL, ucc.user_id, uctd.user_id) AS coach_id'))
            ->addSelect(DB::raw('IF (uctd2.user_id IS NULL, ucc2.user_id, uctd2.user_id) AS supporting_coach_id'));
    }

    public function name(): string
    {
        return $this->name ?? $this->class->name;
    }

    public function description(): string
    {
        return $this->description ?? $this->class->description;
    }

    public function meetingUrl(): ?string
    {
        return $this->meeting_url ?? $this->class->meeting_url;
    }

    public function startTime(): string
    {
        return $this->start_time?->format('H:i:s') ?? $this->class->start_time->format('H:i:s');
    }

    public function endTime(): string
    {
        return $this->end_time?->format('H:i:s') ?? $this->class->end_time->format('H:i:s');
    }

    public function timezone(): Timezone
    {
        return $this->class->location->timezone ?? $this->class->tenant->timezone;
    }

    public function bookingThresholdInDays(): int
    {
        return $this->class->booking_threshold ?? $this->class->tenant->booking_threshold;
    }

    public function attendanceLimit(): int
    {
        return $this->class_limit ?? $this->class->class_limit;
    }

    public function bookingThreshold(): Carbon
    {
        $startTime = $this->start_time
            ? $this->start_time->format('H:i:s')
            : $this->class->start_time->format('H:i:s');

        $dateTime = $this->class_date->setTimeFromTimeString(
            $startTime
        );

        return $dateTime->subMinutes($this->class->booking_threshold);
    }

    public function cancellationThreshold(): Carbon
    {
        $startTime = $this->start_time
            ? $this->start_time->format('H:i:s')
            : $this->class->start_time->format('H:i:s');

        $dateTime = $this->class_date->setTimeFromTimeString(
            $startTime
        );

        return $dateTime->subMinutes($this->class->cancellation_threshhold);
    }

    public function buildDateTime(): Carbon
    {
        return $this->class_date->setTimeFromTimeString(
            $this->startTime()
        )->setTimeZone(
            $this->timezone()->zone
        );
    }

    public function getDayOfWeekAttribute(): DayOfWeek
    {
        return DayOfWeek::tryFromName($this->class_date->format('l'));
    }

    public function minBookedMembersCount(): Attribute
    {
        return Attribute::make(
            get: fn () => Arr::get($this->attributes, 'min_booked_members_count') ?? $this->class->min_booked_members_count,
        );
    }

    public function autoCancelThresholdMin(): Attribute
    {
        return Attribute::make(
            get: fn () => Arr::get($this->attributes, 'auto_cancel_threshold_min') ?? $this->class->auto_cancel_threshold_min,
        );
    }
}
