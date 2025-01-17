<?php

namespace App\Models;

use App\Enums\ScheduleUserAction as ScheduleUserActionEnum;
use App\Enums\ScheduleUserActionStatus;
use App\Traits\BelongsToTenant;
use App\Traits\MutatesBoxId;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ScheduleUserAction extends Model
{
    use BelongsToTenant, HasFactory, MutatesBoxId, Paginatable;

    protected $table = 'scheduled_user_actions';

    protected $primaryKey = 'task_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    protected $casts = [
        'action' => ScheduleUserActionEnum::class,
        'status' => ScheduleUserActionStatus::class,
        'exclude_from_future_batches' => 'integer',
        'extend_package_end_date' => 'integer',
        'date' => 'date',
        'last_debit_date' => 'date',
        'on_hold_release_date' => 'date',
        'on_hold_pro_rata_fee' => 'float',
    ];

    /**
     * Mutate extend_package_end_date to is_extend_package_end_date
     */
    protected function isExtendPackageEndDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extend_package_end_date,
            set: fn (mixed $value) => ['extend_package_end_date' => $value]
        );
    }

    /**
     * Mutate exclude_from_future_batches to is_excluded_from_future_batches
     */
    protected function isExcludedFromFutureBatches(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exclude_from_future_batches,
            set: fn (mixed $value) => ['exclude_from_future_batches' => $value]
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', '=', 'pending');
    }

    public function scopeSearchUser(Builder $query, $searchTerm): Builder
    {
        return $query->join('users AS searchScheduledUser', 'searchScheduledUser.user_id', '=', 'scheduled_user_actions.user_id')
            ->where(DB::raw("CONCAT(searchScheduledUser.name, ' ', searchScheduledUser.surname)"), 'LIKE', '%'.$searchTerm.'%')
            ->orWhere('searchScheduledUser.name', 'LIKE', '%'.$searchTerm.'%')
            ->orWhere('searchScheduledUser.surname', 'LIKE', '%'.$searchTerm.'%')
            ->orWhere('searchScheduledUser.email', 'LIKE', '%'.$searchTerm.'%');
    }
}
