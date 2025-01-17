<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum InvoiceStatus: string
{
    use SmartEnum;

    case OUTSTANDING = 'outstanding';
    case FAILED = 'failed';
    case UNPAID = 'unpaid';
    case CANCELLED = 'cancelled';
    case PAID = 'paid';
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case CREDITED = 'credited';
    case ISSUED = 'issued';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public function toString(): string
    {
        return match ($this) {
            self::OUTSTANDING => 'Outstanding',
            self::FAILED => 'Failed',
            self::UNPAID => 'Unpaid',
            self::CANCELLED => 'Cancelled',
            self::PAID => 'Paid',
            self::PENDING => 'Pending',
            self::SUBMITTED => 'Submitted',
            self::CREDITED => 'Credited',
            self::ISSUED => 'Issued',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially refunded',
        };
    }
}
