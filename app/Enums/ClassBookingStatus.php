<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ClassBookingStatus: int
{
    use SmartEnum;

    case BOOKED = 1;
    case CANCELLED = 2;
    case CANCELLED_AFTER_THRESHOLD = 3;
    case CANCELLED_BY_COACH = 4;
    case NO_SHOW = 5;
    case CHECKED_IN = 6;

    const ALL_CANCELLED_STATUSES = [self::CANCELLED, self::CANCELLED_AFTER_THRESHOLD, self::CANCELLED_BY_COACH];
}
