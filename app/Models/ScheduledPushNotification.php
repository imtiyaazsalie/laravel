<?php

namespace App\Models;

use App\Traits\MutatesBoxFacilityId;
use App\Traits\MutatesBoxId;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledPushNotification extends Model
{
    use HasFactory, MutatesBoxFacilityId, MutatesBoxId, Paginatable;

    protected $table = 'scheduled_push';

    protected $primaryKey = 'scheduled_push_id';

    public $timestamps = true;

    public const CREATED_AT = 'createdOn';

    public const UPDATED_AT = 'updated_at';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'failed_attempts' => 'integer',
    ];
}
