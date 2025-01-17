<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum InjuryStatus: string
{
    use SmartEnum;

    case INJURED = 'injured';
    case HEALED = 'healed';
    case DELETED = 'deleted';
}
