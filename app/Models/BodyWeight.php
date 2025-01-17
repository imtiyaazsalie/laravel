<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodyWeight extends Model
{
    use HasFactory;

    protected $table = 'body_weight';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'recorded_on' => 'datetime',
        'weight' => 'float',
    ];

    public function userLocation(): HasMany
    {
        return $this->hasMany(LocationUser::class, 'user_id', 'user_id');
    }

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
