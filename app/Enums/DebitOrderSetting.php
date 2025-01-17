<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum DebitOrderSetting: string
{
    use SmartEnum;

    case ON_DATE_OF_DEBIT_ORDER = 'on the date of debit-order';
    case FIRST_OF_NEXT_MONTH = 'first day of the next month';
    case DATE_OF_DEBIT_ORDER = 'date of debit-order';
}
