<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ScheduleUserActionStatus: string
{
    use SmartEnum;

    case CANCELLED = 'cancelled';
    case COMPLETE = 'complete';
    case PENDING = 'pending';
}
