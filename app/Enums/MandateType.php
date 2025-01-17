<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum MandateType: int
{
    use SmartEnum;

    case SEPA = 1;
}
