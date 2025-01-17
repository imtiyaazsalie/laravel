<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kirschbaum\PowerJoins\PowerJoins;

/**
 * @property int $wod_capture_id
 * @property int $exercise_id
 * @property int $verifier_id
 * @property string $score
 * @property string $note
 * @property bool $is_pb
 * @property bool $is_rx
 * @property bool $is_verified
 * @property bool $is_active
 * @property \Carbon\Carbon $dt_verified
 */
class WodCaptureExercise extends Model
{
    use HasFactory, PowerJoins;

    public $timestamps = false;

    protected $table = 'wod_capture_exercises';

    protected $primaryKey = 'wod_capture_exercise_id';

    protected $guarded = [];

    protected $casts = [
        'is_pb' => 'integer',
        'is_active' => 'integer',
        'is_verified' => 'integer',
    ];

    protected $attributes = [
        'is_pb' => 0,
        'is_active' => 1,
        'is_verified' => 0,
    ];

    protected function isPersonalBest(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_pb,
            set: fn (mixed $value) => ['is_pb' => $value]
        );
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    public function capture(): BelongsTo
    {
        return $this->belongsTo(WodCapture::class, 'wod_capture_id', 'wod_capture_id');
    }

    public function scopeBenchmarks(Builder $query): Builder
    {
        return $query->joinRelationship('exercise')
            ->where('wod_capture_exercises.is_rx', '=', true)
            ->where('exercise.is_benchmark', '=', true);
    }

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->where('wod_capture_exercises.is_verified', '=', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('wod_capture_exercises.is_active', true);
    }

    public function scopeUnverifiedBenchmarks(Builder $query): Builder
    {
        return $query->active()->unverified()->benchmarks();
    }

    public function isNotPersonalBest(): bool
    {
        return (bool) ! $this->is_pb;
    }

    public function isRx(): bool
    {
        return (bool) $this->is_rx;
    }

    public function isNotRx(): bool
    {
        return ! $this->isRx();
    }
}
