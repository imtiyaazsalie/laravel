<?php

namespace App\Models;

use App\Casts\Serialize;
use App\Traits\MutatesBoxFacilityId;
use App\Traits\MutatesBoxId;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledEmail extends Model
{
    use HasFactory, MutatesBoxFacilityId, MutatesBoxId, Paginatable;

    protected $table = 'scheduled_emails';

    protected $primaryKey = 'scheduled_email_id';

    public $timestamps = true;

    public const CREATED_AT = 'createdOn';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>Do y
     */
    protected $casts = [
        'cc' => Serialize::class,
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'failed_attempts' => 'integer',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(ScheduledEmailAttachment::class, 'scheduled_email_id');
    }
}
