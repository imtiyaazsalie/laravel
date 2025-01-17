<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\IsOwnedByUser;
use App\Traits\Paginatable;
use App\Traits\RecordUserOnCreateAndUpdate;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClassRecurringBooking extends Model
{
    use BelongsToTenant,HasFactory, IsOwnedByUser, Paginatable, RecordUserOnCreateAndUpdate;

    protected $table = 'class_recurring_bookings';

    protected $primaryKey = 'class_recurring_booking_id';

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    public const UPDATED_BY_ID = null;

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'integer',
        'dt_deactivate' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->class->box_id,
        );
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

    public function daysOfWeek(): BelongsToMany
    {
        return $this->belongsToMany(
            ClassDay::class,
            'class_recurring_bookings_days_of_week',
            'class_recurring_booking_id',
            'class_day_id'
        );
    }

    public function dayOfWeek(): HasOne
    {
        return $this->hasOne(ClassRecurringBookingsDaysOfWeek::class, 'class_recurring_booking_id');
    }

    public function isDeactivatedOnDate($date)
    {
        return $this->dt_deactivate && $this->dt_deactivate < $date;
    }

    public function hasDayOfWeek($dayName): bool
    {
        foreach ($this->daysOfWeek()->get() as $dayOfWeek) {
            if ($dayOfWeek->name === $dayName) {
                return true;
            }
        }

        return false;
    }
}
