<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum MailerSchedule: string
{
    use SmartEnum;

    case NOW = 'now';
    case ONCEOFF = 'onceoff';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
}
