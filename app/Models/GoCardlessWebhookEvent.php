<?php

namespace App\Models;

use App\Casts\Serialize;
use App\Enums\GoCardlessWebhookStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoCardlessWebhookEvent extends Model
{
    use HasFactory;

    protected $table = 'go_cardless_webhook_events';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = true;

    protected $guarded = [];

    const UPDATED_AT = null;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => GoCardlessWebhookStatus::class,
        'payload' => Serialize::class,
    ];
}
