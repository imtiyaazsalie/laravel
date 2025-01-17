<?php

namespace App\Models;

use App\Enums\ClassBookingWaitingStatus;
use App\Traits\BelongsToTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassBookingWaitingList extends Model
{
    use BelongsToTenant, HasFactory, Paginatable;

    protected $table = 'class_booking_waiting_list';

    public $timestamps = true;

    protected $primaryKey = 'class_booking_waiting_id';

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $guarded = [];

    protected $casts = [
        'status' => ClassBookingWaitingStatus::class,
    ];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->class()->exists() ? $this->class->box_id : null,
        );
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function classDate(): BelongsTo
    {
        return $this->belongsTo(ClassDate::class, 'class_to_date_id');
    }

    public function userPackage(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class, 'user_package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id', 'user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id', 'user_id');
    }
}
