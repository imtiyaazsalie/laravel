<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum UserDebitStatus: int
{
    use SmartEnum;

    case CASH = 1;
    case DEBIT_ORDER = 2;
    case DISCOVERY_VITALITY = 3;
    case NO_PAYMENT = 4;
    case UP_FRONT_PAYMENT = 5;
    case ONLINE_PAYMENT = 6;
    case TOKENIZED_CARD = 7;

    public function toString(): string
    {
        return match ($this) {
            self::CASH => 'Cash/EFT/Card',
            self::DEBIT_ORDER => 'Debit order',
            self::DISCOVERY_VITALITY => 'Discovery Vitality',
            self::NO_PAYMENT => 'No payment',
            self::UP_FRONT_PAYMENT => 'Up-front payment',
            self::ONLINE_PAYMENT => 'Online Payment (Separate to Octiv)',
            self::TOKENIZED_CARD => 'Tokenized Card',
        };
    }
}
