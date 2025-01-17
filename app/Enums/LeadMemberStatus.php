<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum LeadMemberStatus: string
{
    use SmartEnum;

    case CONTACTED = 'contacted';
    case CONVERTED = 'converted';
    case DROP_IN = 'drop_in';
    case FOLLOW_UP = 'follow_up';
    case NEEDS_FOLLOW_UP = 'needs_follow_up';
    case OTHER = 'other';
    case PENDING = 'pending';
    case REQUEST_DEMO = 'request_demo';
    case ON_RAMP_COMPLETED = 'on_ramp_completed';
    case ON_RAMP_STARTED = 'on_ramp_started';

}
