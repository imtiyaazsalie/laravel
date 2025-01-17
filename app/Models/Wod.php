<?php

namespace App\Models;

use App\Models\Scopes\OwnedByTenantScope;
use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Kirschbaum\PowerJoins\PowerJoins;

/**
 * @property int|string $tenant_id
 * @property int|string $programme_id
 * @property string $name
 * @property string $nickname
 * @property string $description
 * @property string $image
 * @property string $warmup
 * @property string $cooldown
 * @property string $coach_notes
 * @property string $member_notes
 * @property \Carbon\Carbon $date
 */
class Wod extends Model
{
    use HasFactory, IsOwnedByTenant, Paginatable, PowerJoins;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = null;

    public $timestamps = true;

    protected $table = 'wods';

    protected $primaryKey = 'wod_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new OwnedByTenantScope);
    }

    protected $casts = [
        'wod_date' => 'date',
    ];

    protected $guarded = [];

    protected function coolDown(): Attribute
    {
        return Attribute::make(
            get: fn () => Arr::get($this->attributes, 'cooldown'),
            set: fn (mixed $value) => ['cooldown' => $value]
        );
    }

    protected function warmUp(): Attribute
    {
        return Attribute::make(
            get: fn () => Arr::get($this->attributes, 'warmup'),
            set: fn (mixed $value) => ['warmup' => $value]
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->wod_name,
            set: fn (mixed $value) => ['wod_name' => $value]
        );
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->descr,
            set: fn (mixed $value) => ['descr' => $value]
        );
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->img,
            set: fn (mixed $value) => ['img' => $value]
        );
    }

    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->wod_date,
            set: fn (mixed $value) => ['wod_date' => $value]
        );
    }

    public function exercises(): HasMany
    {
        return $this->allExercises()
            ->active();
    }

    public function allExercises(): HasMany
    {
        return $this->hasMany(WodExercise::class, 'wod_id')
            ->orderBy('wte_order');
    }

    public function captures(): HasMany
    {
        return $this->hasMany(WodCapture::class, 'wod_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'box_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function scopeStartsAfter(Builder $query, $date): Builder
    {
        return $query->where('wod_date', '>=', $date);
    }

    public function scopeEndsBefore(Builder $query, $date): Builder
    {
        return $query->where('wod_date', '<', $date);
    }

    public function scopeBeforeWorkoutThreshold(Builder $query): Builder
    {
        return $query->joinRelationship('tenant')
            ->where('wod_date', '<=', DB::raw('DATE_ADD(CURDATE(), INTERVAL boxes.workout_threshold DAY)'));
    }
}
