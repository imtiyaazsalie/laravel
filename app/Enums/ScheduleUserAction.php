<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ScheduleUserAction: string
{
    use SmartEnum;

    case PLACE_ON_HOLD = 'onHold';
    case DEACTIVATE = 'deactivate';
}
