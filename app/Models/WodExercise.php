<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $wod_id
 * @property int $exercise_id
 * @property bool $is_active
 * @property int $wte_order
 */
class WodExercise extends Model
{
    use HasFactory;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = null;

    public $timestamps = true;

    protected $table = 'wod_to_exercise';

    protected $primaryKey = 'wod_to_exercise_id';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Mutate wte_order to order
     */
    protected function order(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->wte_order,
            set: fn (mixed $value) => ['wte_order' => $value]
        );
    }

    public function prefixes(): HasManyThrough
    {
        return $this->hasManyThrough(WodExercisePrefix::class, WodExercise::class, 'wod_to_exercise_id', 'wod_to_exercise_id');
    }

    public function prefix(): HasOne
    {
        return $this->hasOne(WodExercisePrefix::class, 'wod_to_exercise_id', 'wod_to_exercise_id')
            ->where('is_active', true)
            ->latest();
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', '=', true);
    }
}
