<?php

namespace App\Models;

use App\Models\Scopes\OwnedByTenantScope;
use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Exercise extends Model
{
    use HasFactory, IsOwnedByTenant, Paginatable;

    protected $table = 'exercise';

    protected $primaryKey = 'exercise_id';

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $guarded = [];

    protected $casts = [
        'is_benchmark' => 'integer',
        'is_active' => 'integer',
        'is_pb' => 'integer',
    ];

    protected $attributes = [
        'is_active' => 1,
    ];

    /**
     * Mutate exercise_name to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exercise_name,
            set: fn (mixed $value) => ['exercise_name' => $value]
        );
    }

    /**
     * Mutate exercise_desc to description
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exercise_desc,
            set: fn (mixed $value) => ['exercise_desc' => $value]
        );
    }

    /**
     * Mutate exercise_category_id to category
     */
    protected function category(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exercise_category_id,
            set: fn (mixed $value) => ['exercise_category_id' => $value]
        );
    }

    protected function isPersonalBest(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_pb,
            set: fn (mixed $value) => ['is_pb' => $value]
        );
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new OwnedByTenantScope);
    }

    public function scopeBenchmarks(Builder $query): Builder
    {
        return $query->where('is_benchmark', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('box_id');
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'box_id', 'box_id');
    }

    public function exerciseCategory(): HasOne
    {
        return $this->hasOne(ExerciseCategory::class, 'exercise_category_id', 'exercise_category_id');
    }

    public function measureUnit(): HasOne
    {
        return $this->hasOne(MeasurementUnit::class, 'measuring_unit_id', 'measuring_unit_id');
    }

    public function scopeType(Builder $query, $type): Builder
    {
        if ($type == 'global') {
            $query->whereNull('box_id')->withoutGlobalScopes();
        }

        if ($type == 'owned') {
            $query->whereNotNull('box_id');
        }

        return $query;

    }

    public function isBenchmark(): bool
    {
        return (bool) $this->is_benchmark;
    }

    public function isNotBenchmark(): bool
    {
        return ! $this->isBenchmark();
    }

    public function isNotPersonalBest(): bool
    {
        return (bool) ! $this->is_pb;
    }

    public function toggleStatus()
    {
        $this->update(['is_active' => ! $this->is_active]);
    }

    public function isGlobal(): bool
    {
        return is_null($this->tenant_id);
    }
}
