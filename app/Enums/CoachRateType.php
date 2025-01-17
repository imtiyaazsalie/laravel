<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum CoachRateType: string
{
    use SmartEnum;

    case SESSIONS = 'pt_sessions';
    case CLASSES = 'classes';
}
