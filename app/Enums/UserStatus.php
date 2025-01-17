<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum UserStatus: int
{
    use SmartEnum;

    case PENDING = 1;
    case ACTIVE = 2;
    case SUSPENDED = 3;
    case DEACTIVATED = 4;
    case READY_FOR_TRANSFER = 5;
    case ON_HOLD = 6;

    public static function allStatuses(): array
    {
        return [
            self::PENDING,
            self::ACTIVE,
            self::SUSPENDED,
            self::DEACTIVATED,
            self::READY_FOR_TRANSFER,
            self::ON_HOLD,
        ];
    }

    public function toString(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::DEACTIVATED => 'Deactivated',
            self::READY_FOR_TRANSFER => 'Ready for Transfer',
            self::ON_HOLD => 'On-hold'
        };
    }
}
