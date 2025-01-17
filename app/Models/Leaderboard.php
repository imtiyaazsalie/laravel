<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leaderboard extends Model
{
    use BelongsToTenant, HasFactory, Paginatable;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    public $timestamps = true;

    protected $table = 'leaderboard';

    protected $primaryKey = 'leaderboard_id';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'integer',
    ];

    protected $attributes = [
        'is_active' => 1,
    ];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exercise()->exists() ? $this->exercise->box_id : null,
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
