<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum PosStatus: string
{
    use SmartEnum;

    case CANCELLED = 'cancelled';
    case INVOICED = 'invoiced';
    case OPEN = 'open';
    case PAID = 'paid';
    case PARKED = 'parked';
}
