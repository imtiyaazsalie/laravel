<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum Affiliate: int
{
    use SmartEnum;

    case CrossFit = 1;
}
