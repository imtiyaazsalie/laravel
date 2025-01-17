<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ClassBookingWaitingStatus: string
{
    use SmartEnum;

    case WAITING = 'waiting';
    case CANCELLED = 'cancelled';
    case BOOKED = 'booked';
}
