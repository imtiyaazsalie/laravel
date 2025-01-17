<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum PaymentType: string
{
    use SmartEnum;

    case CASH = 'cash';
    case CARD = 'card';
    case EFT = 'eft';
    case ADHOC = 'adhocPayment';
    case INVOICE_ONLY = 'invoiceOnly';
    case DEBIT_ORDER = 'debitOrder';
    case NO_PAYMENT = 'noPayment';

    public static function posTypes(): array
    {
        return [
            self::CASH->value,
            self::CARD->value,
            self::EFT->value,
            self::DEBIT_ORDER->value,
            self::ADHOC->value,
            self::INVOICE_ONLY->value,
        ];
    }

    public static function userPackageTypes(): array
    {
        return [
            self::CASH->value,
            self::CARD->value,
            self::EFT->value,
            self::ADHOC->value,
            self::INVOICE_ONLY->value,
        ];
    }
}
