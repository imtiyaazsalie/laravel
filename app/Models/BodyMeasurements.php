<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyMeasurements extends Model
{
    protected $table = 'body_measurement';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'recorded_on' => 'datetime',
        'tricep' => 'float',
        'subscapular' => 'float',
        'abdominal' => 'float',
        'suprailiac' => 'float',
        'thigh' => 'float',
        'calf' => 'float',
        'body_fat_percentage' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id', 'user_id');
    }

    /**
     * Mutate recorded_on to recorded_at
     */
    protected function recordedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->recorded_on,
            set: fn (mixed $value) => ['recorded_on' => $value]
        );
    }
}
