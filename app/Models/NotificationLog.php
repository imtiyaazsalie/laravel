<?php

namespace App\Models;

use App\Enums\NotificationLogStatus;
use App\Enums\NotificationLogType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'cc' => 'array',
        'attachment' => 'array',
        'status' => NotificationLogStatus::class,
        'type' => NotificationLogType::class,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'box_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'box_facility_id');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notifications::class, 'notification_id');
    }

    public function mailer(): BelongsTo
    {
        return $this->belongsTo(Mailer::class, 'mailer_id');
    }
}
