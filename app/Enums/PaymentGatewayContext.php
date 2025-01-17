<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum PaymentGatewayContext: string
{
    use SmartEnum;

    case DEBIT_ORDER = 'debit_order';
    case AD_HOC = 'adhoc_payment';
}
