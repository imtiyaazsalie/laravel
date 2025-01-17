<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum FinancePaymentTokenType: string
{
    use SmartEnum;

    case USER = 'user';
    case LOCATION = 'location';
}
