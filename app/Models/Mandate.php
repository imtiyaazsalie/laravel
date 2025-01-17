<?php

namespace App\Models;

use App\Enums\MandateStatus;
use App\Enums\MandateType;
use App\Traits\IsOwnedByTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mandate extends Model
{
    use IsOwnedByTenant;

    protected $guarded = [];

    protected $casts = [
        'type' => MandateType::class,
        'status' => MandateStatus::class,
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
