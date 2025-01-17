<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\MutatesBoxId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserOnHold extends Model
{
    use BelongsToTenant, HasFactory, MutatesBoxId, SoftDeletes;

    protected $table = 'users_on_hold';

    protected $primaryKey = 'users_on_hold_id';

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'release_date' => 'date',
        'extend_package_end_date' => 'integer',
    ];

    protected $attributes = [
        'is_on_hold' => true,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
