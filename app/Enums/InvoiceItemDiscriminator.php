<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum InvoiceItemDiscriminator: string
{
    use SmartEnum;

    case PRORATE = 'prorate';
    case MEMBERSHIP = 'membership';
    case DISCOUNT = 'discount';
    case SPECIAL_DISCOUNT = 'special_discount';
}
