<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ThreePeaksPaidOrUnpaidStatus: int
{
    use SmartEnum;

    case NOT_DONE = 0;
    case PROCESSED_TO_BANK = 1;
    case UNPAID = 2;
    case LATE_UNPAID = 3;

    public function toString(): string
    {
        return match ($this) {
            self::NOT_DONE => 'Not done / Check CDV and BPRO',
            self::PROCESSED_TO_BANK => 'Processed to bank',
            self::UNPAID => 'Unpaid',
            self::LATE_UNPAID => 'Late Unpaid',
        };
    }
}
