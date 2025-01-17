<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationUnsubscriptions extends Model
{
    protected $table = 'crm_notification_unsubscriptions';

    protected $primaryKey = 'notification_subscription_id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = null;
}
