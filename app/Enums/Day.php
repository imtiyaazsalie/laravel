<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum Day: int
{
    use SmartEnum;

    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;
    case SATURDAY = 6;
    case SUNDAY = 7;
    case PUBLIC_HOLIDAY = 8;
}
