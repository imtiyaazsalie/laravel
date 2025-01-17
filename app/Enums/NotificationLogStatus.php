<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum NotificationLogStatus: int
{
    use SmartEnum;

    case CANCELLED = 1;

    case FAILED = 2;

}
