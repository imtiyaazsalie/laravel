<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $wod_capture_id
 * @property int $created_by_id
 * @property string $content
 */
class WodCaptureComments extends Model
{
    use HasFactory;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $timestamps = true;

    protected $table = 'wod_capture_comments';

    protected $primaryKey = 'comment_id';

    protected $guarded = [];

    protected $attributes = [
        'deleted' => false,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function capture(): HasOne
    {
        return $this->hasOne(WodCapture::class, 'wod_capture_id', 'wod_capture_id');
    }
}
