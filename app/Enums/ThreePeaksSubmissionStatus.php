<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ThreePeaksSubmissionStatus: int
{
    use SmartEnum;

    case BUSY_OR_NONE = 0;
    case CDV_COMPLETED_AND_WAITING = 1;
    case PROCESSED_TO_BANK = 2;
    case PAID = 3;
    case ALL_RECORDS_REJECTED = 4;
    case RECALLED_OR_CANCELLED = 99;

    public function toString(): string
    {
        return match ($this) {
            self::BUSY_OR_NONE => 'Busy/None',
            self::CDV_COMPLETED_AND_WAITING => 'CDV Completed and Waiting',
            self::PROCESSED_TO_BANK => 'Processed to Bank',
            self::PAID => 'Paid',
            self::ALL_RECORDS_REJECTED => 'All records Rejected',
            self::RECALLED_OR_CANCELLED => 'Recalled or Cancelled',
        };
    }
}
