<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kirschbaum\PowerJoins\PowerJoins;

/**
 * @property int $wod_id
 * @property int $user_id
 * @property int $created_by_id
 * @property int $verified_by_id
 * @property bool $is_verified
 * @property \Carbon\Carbon $dt_verified
 */
class WodCapture extends Model
{
    use BelongsToTenant, HasFactory, Paginatable, PowerJoins;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = null;

    public $timestamps = true;

    protected $table = 'wod_capture';

    protected $primaryKey = 'wod_capture_id';

    protected $guarded = [];

    protected $casts = [
        'is_verified' => 'integer',
        'dt_verified' => 'datetime',
    ];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->wod()->exists() ? $this->wod->box_id : null,
        );
    }

    /**
     * Mutate capturer_id to created_by_id
     */
    protected function createdById(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->capturer_id,
            set: fn (mixed $value) => ['capturer_id' => $value]
        );
    }

    public function wod(): BelongsTo
    {
        return $this->belongsTo(Wod::class, 'wod_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(WodCaptureLikes::class, 'wod_capture_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WodCaptureComments::class, 'wod_capture_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WodCaptureExercise::class, 'wod_capture_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capturer_id', 'user_id')
            ->withTrashed();
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id', 'user_id')
            ->withTrashed();
    }
}
