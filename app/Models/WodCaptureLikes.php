<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $wod_capture_id
 * @property int $user_id
 */
class WodCaptureLikes extends Model
{
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $table = 'wod_capture_likes';

    protected $primaryKey = 'like_id';

    protected $guarded = [];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->wodCapture()->exists() ? $this->wodCapture?->wod->box_id : null,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wodCapture(): BelongsTo
    {
        return $this->belongsTo(WodCapture::class, 'wod_capture_id', 'wod_capture_id');
    }
}
