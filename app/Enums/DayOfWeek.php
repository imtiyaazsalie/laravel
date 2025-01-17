<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum DayOfWeek: int
{
    use SmartEnum;

    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;
    case SATURDAY = 6;
    case SUNDAY = 7;

    public static function tryFromName(string $name): DayOfWeek
    {
        return match (strtoupper($name)) {
            'MONDAY' => self::MONDAY,
            'TUESDAY' => self::TUESDAY,
            'WEDNESDAY' => self::WEDNESDAY,
            'THURSDAY' => self::THURSDAY,
            'FRIDAY' => self::FRIDAY,
            'SATURDAY' => self::SATURDAY,
            'SUNDAY' => self::SUNDAY,
        };
    }

    public function toString(): string
    {
        return match ($this) {
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
            self::SUNDAY => 'Sunday',
        };
    }
}
