<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|string $wod_to_exercise_id
 * @property string $prefix
 * @property bool $is_active
 */
class WodExercisePrefix extends Model
{
    use HasFactory;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    public $timestamps = true;

    protected $table = 'wod_exercise_prefix';

    protected $primaryKey = 'wod_exercise_prefix_id';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'integer',
    ];

    protected $attributes = [
        'is_active' => 1,
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->table.'.is_active', '=', true);
    }
}
