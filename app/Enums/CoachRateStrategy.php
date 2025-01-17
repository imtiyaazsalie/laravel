<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum CoachRateStrategy: string
{
    use SmartEnum;

    case MEMBER_BASED = 'member_based_rate';
    case FLAT = 'flat_rate';
}
