<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ThreePeaksApiProcessingStatus: int
{
    use SmartEnum;

    case NOT_RECEIVED = 0;
    case RECEIVED = 1;
    case VALIDATION_IN_PROGRESS = 2;
    case VALIDATION_PASSED = 3;
    case VALIDATION_FAILED = 4;

    public function toString(): string
    {
        return match ($this) {
            self::NOT_RECEIVED => 'Not received',
            self::RECEIVED => 'Received',
            self::VALIDATION_IN_PROGRESS => 'Validation in progress',
            self::VALIDATION_PASSED => 'Validation passed',
            self::VALIDATION_FAILED => 'Validation failed',
        };
    }
}
