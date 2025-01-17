<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum InvoiceDiscriminator: string
{
    use SmartEnum;

    case INVOICE = 'invoice';
    case TOPUP_INVOICE = 'topupInvoice';
    case SIGN_UP_INVOICE = 'signUpInvoice';
    case DROP_IN_INVOICE = 'dropInInvoice';
    case BUY_PACKAGE_INVOICE = 'buyPackageInvoice';
    case ALLOCATED_PACKAGE_INVOICE = 'allocatedPackageInvoice';
    case LATE_CANCELLATION_INVOICE = 'late_cancellation_invoice';
    case NO_SH0W_FEE_INVOICE = 'no_show_fee_invoice';
}
