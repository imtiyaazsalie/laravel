<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum HealthCareProvider: int
{
    use SmartEnum;

    case DISCOVERY_VITALITY_ID = 1;
    case MOMENTUM_MULTIPLY_ID = 2;
}
