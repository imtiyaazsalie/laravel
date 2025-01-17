<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum Gender: int
{
    use SmartEnum;

    case MALE = 1;
    case FEMALE = 2;
    case OTHER = 3;
}
