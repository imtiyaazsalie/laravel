<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ThreePeaksBankProcessingStatus: int
{
    use SmartEnum;

    case NOT_DONE = 0;
    case BANK_ACCEPTED = 3;
    case REJECTED = 4;

    public function toString(): string
    {
        return match ($this) {
            self::NOT_DONE => 'Not done/Check CDV',
            self::BANK_ACCEPTED => 'Bank Accepted',
            self::REJECTED => 'Rejected',
        };
    }
}
