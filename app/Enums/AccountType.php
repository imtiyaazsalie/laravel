<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum AccountType: int
{
    use SmartEnum;

    case CHEQUE_CURRENT = 1;
    case SAVINGS = 2;
    case TRANSMISSION = 3;
    case BOND = 4;
    case SUBSCRIPTION = 5;

    public function toString(): string
    {
        return str($this->name)->title()->replace('_', '/')->toString();
    }

    public static function tryFromImportCode($code): ?AccountType
    {
        try {
            return self::fromImportCode($code);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function fromImportCode($code): AccountType
    {
        $map = [
            self::CHEQUE_CURRENT->value => 'C',
            self::SAVINGS->value => 'S',
            self::TRANSMISSION->value => 'T',
            self::BOND->value => 'B',
            self::SUBSCRIPTION->value => 'Su',
        ];

        $index = array_search($code, $map);

        return self::from($index);
    }

    public function importCode(): string
    {
        return match ($this) {
            self::CHEQUE_CURRENT => 'C',
            self::SAVINGS => 'S',
            self::TRANSMISSION => 'T',
            self::BOND => 'B',
            self::SUBSCRIPTION => 'Su'
        };
    }

    public function namibiaExportCode(): string
    {
        return match ($this) {
            self::CHEQUE_CURRENT => 1,
            self::SAVINGS => 2,
            self::TRANSMISSION => 3,
            self::BOND => 4,
            self::SUBSCRIPTION => 6
        };
    }
}
