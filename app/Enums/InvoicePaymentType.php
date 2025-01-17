<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum InvoicePaymentType: string
{
    use SmartEnum;

    case ADHOC = 'adhoc';
    case CARD = 'card';
    case CASH = 'cash';
    case DEBIT_ORDER = 'debit_order';
    case EFT = 'eft';
    case REFUND = 'refund';
}
