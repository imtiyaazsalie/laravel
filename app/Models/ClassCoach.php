<?php

namespace App\Models;

use App\Enums\CoachType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCoach extends Model
{
    protected $table = 'class_coaches';

    protected $primaryKey = 'class_coach_id';

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $guarded = [];

    protected $casts = [
        'coach_type_id' => CoachType::class,
        'is_active' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id')->withTrashed();
    }

    /**
     * Mutate coach_type_id to type
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->coach_type_id,
            set: fn (mixed $value) => ['coach_type_id' => $value]
        );
    }

    /**
     * Mutate coach_id to user_id
     */
    protected function userId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->coach_id,
            set: fn (mixed $value) => ['coach_id' => $value]
        );
    }
}
