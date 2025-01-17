<?php

namespace App\Models;

use App\Enums\ClassBookingStatus;
use App\Enums\PackageType;
use App\Traits\BelongsToTenant;
use App\Traits\Paginatable;
use App\Traits\RecordUserOnCreateAndUpdate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Notifications\Notifiable;
use Kirschbaum\PowerJoins\PowerJoins;

class ClassBooking extends Model
{
    use BelongsToTenant, HasFactory, Notifiable, Paginatable, PowerJoins, RecordUserOnCreateAndUpdate;

    protected $table = 'class_bookings';

    protected $primaryKey = 'class_booking_id';

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_checked_in' => 'integer',
        'is_checked_out' => 'integer',
        'top_up_used' => 'integer',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'class_booking_status_id' => ClassBookingStatus::class,
    ];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this?->class?->box_id,
        );
    }

    /**
     * Mutate class_booking_status_id to status
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->class_booking_status_id,
            set: fn (mixed $value) => ['class_booking_status_id' => $value]
        );
    }

    public function classDate(): BelongsTo
    {
        return $this->belongsTo(ClassDate::class, 'class_to_date_id')->withoutGlobalScopes();
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function leadMember(): BelongsTo
    {
        return $this->belongsTo(LeadMember::class, 'lead_member_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function userPackage(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class, 'user_package_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'class_booking_id');
    }

    public function coronavirusQuestionaireResult(): HasOne
    {
        return $this->hasOne(CoronavirusQuestionnaireResult::class, 'class_booking_id');
    }

    public function isBooked(): bool
    {
        return $this->status === ClassBookingStatus::BOOKED;
    }

    public function isNotBooked(): bool
    {
        return ! $this->isBooked();
    }

    public function isNoShow(): bool
    {
        return $this->status === ClassBookingStatus::NO_SHOW;
    }

    public function isNotNoShow(): bool
    {
        return ! $this->isNoShow();
    }

    public function shouldRefund(): bool
    {
        $this->loadMissing(['userPackage', 'class']);

        if ($this->top_up_used) {
            return true;
        }

        if ($this->userPackage
            && ! $this->class->isFree()
            && $this->userPackage->package_limit_type_id === PackageType::LIMITED->value
        ) {
            return true;
        }

        return false;
    }

    public function scopeBy(Builder $query, User|string|int|null $user): Builder
    {
        $userId = $user instanceof User ? $user->getAuthIdentifier() : $user;

        return $query->when($userId, function ($query) use ($userId) {
            $query->where('class_bookings.user_id', $userId);
        });
    }

    public function scopeBetween(Builder $query, string|Carbon $start, string|Carbon $end): Builder
    {
        if (is_string($start)) {
            $start = Carbon::parse($start);
        }

        if (is_string($end)) {
            $end = Carbon::parse($end);
        }

        return $query->joinRelationship('classDate')
            ->where(function ($query) use ($start, $end) {
                $query->where('class_to_dates.class_date', '>=', $start->toDateString())
                    ->where('class_to_dates.class_date', '<=', $end->toDateString());
            });
    }

    public function scopeBeforeOrOn(Builder $query, Carbon $date): Builder
    {
        return $query->joinRelation('classDate')
            ->whereDate('class_to_dates.class_date', '<=', $date);
    }

    public function scopeBooked(Builder $query): Builder
    {
        return $query->where('class_booking_status_id', ClassBookingStatus::BOOKED->value);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('class_booking_status_id', ClassBookingStatus::CANCELLED->value);
    }

    public function scopeCancelledAfterThreshold(Builder $query): Builder
    {
        return $query->where('class_booking_status_id', ClassBookingStatus::CANCELLED_AFTER_THRESHOLD->value);
    }

    public function scopeCancelledByCoach(Builder $query): Builder
    {
        return $query->where('class_booking_status_id', ClassBookingStatus::CANCELLED_BY_COACH->value);
    }

    public function scopeNoShow(Builder $query): Builder
    {
        return $query->where('class_booking_status_id', ClassBookingStatus::NO_SHOW->value);
    }

    public function scopeCheckedIn(Builder $query): Builder
    {
        return $query->where('class_booking_status_id', ClassBookingStatus::BOOKED->value)
            ->where('class_bookings.is_checked_in', true);
    }

    public function scopeStatus(Builder $query, ClassBookingStatus $status): Builder
    {
        if ($status === ClassBookingStatus::CHECKED_IN) {
            return $query->checkedIn();
        }

        return $query->where('class_booking_status_id', $status->value);
    }

    public function scopeForYear(Builder $query, string|int $year): Builder
    {
        $year = Carbon::today()->setYear($year);

        return $query->joinRelationship('classDate')
            ->where(function ($query) use ($year) {
                $query->where('class_to_dates.class_date', '>=', $year->startOfYear()->toDateString())
                    ->where('class_to_dates.class_date', '<=', $year->endOfYear()->toDateString());
            });
    }

    /**
     * Route notifications for the mail channel.
     *
     * @return array<string, string>|string
     */
    public function routeEmailsTo(): Address
    {
        if ($this->user_id) {
            return new Address($this->user->email, $this->user->full_name);
        }

        if ($this->lead_member_id) {
            return new Address($this->user->email_address, $this->leadMember->name);
        }

        // Return email address and name...
        return new Address($this->non_member_email, $this->non_member_name);
    }

    /**
     * Get user name for class booking.
     */
    public function getName(): string
    {
        if ($this->user_id) {
            return $this->user->name;
        }

        if ($this->lead_member_id) {
            return $this->leadMember->first_name;
        }

        return $this->non_member_name;
    }

    /**
     * Get user surname for class booking.
     */
    public function getSurname(): string
    {
        if ($this->user_id) {
            return $this->user->surname;
        }

        if ($this->lead_member_id) {
            return $this->leadMember->last_name;
        }

        return '';
    }
}
