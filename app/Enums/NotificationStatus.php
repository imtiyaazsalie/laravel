<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum NotificationStatus: string
{
    use SmartEnum;

    case ENABLED = 'enabled';
    case DISABLED = 'disabled';
    case DELETED = 'deleted';
}
