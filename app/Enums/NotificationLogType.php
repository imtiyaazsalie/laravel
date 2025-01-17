<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum NotificationLogType: int
{
    use SmartEnum;

    case EMAIL = 1;

    case SMS = 2;

    case PUSH = 3;
}
