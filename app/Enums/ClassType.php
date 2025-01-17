<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ClassType: int
{
    use SmartEnum;

    case ONCE_OFF = 1;
    case RECURRING = 2;
}
