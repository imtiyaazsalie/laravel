<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum CoachType: int
{
    use SmartEnum;

    case HEAD_COACH = 1;
    case SUPPORTING_COACH = 2;
}
