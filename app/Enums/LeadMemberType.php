<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum LeadMemberType: string
{
    use SmartEnum;

    case DROP_IN = 'drop_in';
    case WEBSITE = 'website';
    case REFERRAL = 'referral';
}
