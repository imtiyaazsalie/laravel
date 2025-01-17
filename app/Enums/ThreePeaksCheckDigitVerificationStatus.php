<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ThreePeaksCheckDigitVerificationStatus: int
{
    use SmartEnum;

    case NOT_DONE = 0;
    case ACCEPTED = 3;
    case REJECTED = 4;
    case PROCESSING = 99;

    public function toString(): string
    {
        return match ($this) {
            self::NOT_DONE => 'Not done/Processing',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::PROCESSING => 'Not done/Processing',
        };
    }
}
