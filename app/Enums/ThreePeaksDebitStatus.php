<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ThreePeaksDebitStatus: int
{
    use SmartEnum;

    case WAITING_OR_CANCELLED = 0;
    case BANK_PRE_VALIDATION_REJECT = 1;
    case BANK_PRE_VALIDATION_ACCEPT = 2;
    case BANK_REJECT = 3;
    case BANK_ACCEPT = 4;
    case FUNDS_COLLECTED = 5;
    case FUNDS_UNPAID = 6;
    case FUNDS_LATE_UNPAID = 7;

    public function toString(): string
    {
        return match ($this) {
            self::WAITING_OR_CANCELLED => 'Waiting / Cancelled',
            self::BANK_PRE_VALIDATION_REJECT => 'Bank Pre-Validation Reject',
            self::BANK_PRE_VALIDATION_ACCEPT => 'Bank Pre-Validation Accept',
            self::BANK_REJECT => 'Bank Reject',
            self::BANK_ACCEPT => 'Bank Accept',
            self::FUNDS_COLLECTED => 'Funds Collected from Debtor',
            self::FUNDS_UNPAID => 'Funds Unpaid',
            self::FUNDS_LATE_UNPAID => 'Funds Late Unpaid',
        };
    }
}
