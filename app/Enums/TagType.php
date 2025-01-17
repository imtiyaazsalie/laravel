<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum TagType: string
{
    use SmartEnum;

    case PACKAGE = 'package';
    case LOCATION = 'location';
    case PAYMENT = 'payment_processor';
}
