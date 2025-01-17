<?php

namespace App\Models;

use App\Traits\IsOwnedByTenant;
use App\Traits\MutatesBoxFacilityId;
use App\Traits\Paginatable;
use App\Traits\RecordUserOnCreateAndUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Task extends Model
{
    use HasFactory, IsOwnedByTenant, MutatesBoxFacilityId, Paginatable, RecordUserOnCreateAndUpdate;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $timestamps = true;

    protected $table = 'task_tasks';

    protected $primaryKey = 'task_id';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_completed' => 'integer',
        'due_date' => 'date',
    ];

    public function scopeIsCompleted(Builder $query, bool $value): Builder
    {
        return $query->where('is_completed', '=', $value);
    }

    public function scopeIsScheduled(Builder $query, bool $value = true): Builder
    {
        return $query->when(
            $value,
            fn (Builder $query) => $query->whereNotNull('due_date'),
            fn (Builder $query) => $query->whereNull('due_date')
        );
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function assignees(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, TaskAssignee::class, 'task_id', 'user_id', 'task_id', 'user_id');
    }
}
