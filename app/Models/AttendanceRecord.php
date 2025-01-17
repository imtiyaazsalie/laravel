<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\IsOwnedByLocation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToTenant, HasFactory, IsOwnedByLocation;

    protected $table = 'attendance_records';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'date_of_birth' => 'date',
    ];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->location->box_id,
        );
    }

    public function classBooking(): BelongsTo
    {
        return $this->belongsTo(ClassBooking::class, 'class_booking_id');
    }

    public function classDate(): BelongsTo
    {
        return $this->belongsTo(ClassDate::class, 'class_to_date_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function healthProvider(): BelongsTo
    {
        return $this->belongsTo(HealthCareProvider::class, 'health_provider_id');
    }

    public function getFullNameAttribute(): string
    {
        return $this->name.' '.$this->surname;
    }
}
