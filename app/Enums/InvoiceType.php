<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum InvoiceType: string
{
    use SmartEnum;

    case INVOICE = 'invoice';
    case CREDIT_NOTE = 'creditNote';
}
