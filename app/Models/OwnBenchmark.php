<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnBenchmark extends Model
{
    use BelongsToTenant, HasFactory;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $timestamps = true;

    protected $table = 'own_benchmarks';

    protected $primaryKey = 'own_benchmark_id';

    protected $casts = [
        'score' => 'float',
        'is_rx' => 'integer',
        'is_verified' => 'integer',
        'is_active' => 'integer',
        'own_benchmark_date' => 'date',
        'dt_verified' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => 1,
    ];

    protected $guarded = [];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exercise()->exists() ? $this->exercise->box_id : null,
        );
    }

    /**
     * Mutate verifier_id to verified_by_id
     */
    protected function verifiedById(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->verifier_id,
            set: fn (mixed $value) => ['verifier_id' => $value]
        );
    }

    /**
     * Mutate own_benchmark_date to date
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->own_benchmark_date,
            set: fn (mixed $value) => ['own_benchmark_date' => $value]
        );
    }

    public function score(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? (string) $value : 0,
            set: fn ($value) => $value ? preg_replace('/(?<=\.\d{2})\d+/', '$1', (string) $value) : 0,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isRx(): bool
    {
        return (bool) $this->is_rx;
    }
}
