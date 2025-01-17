<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum PaymentProcessorTag: string
{
    use SmartEnum;

    case SEPA = 'Sepa';
    case STRIPE = 'Stripe';
    case PAY_NOW = 'PayNow';
    case NETCASH = 'Netcash';
    case PAYSTACK = 'Paystack';
    case GOCARDLESS = 'GoCardless';
    case THREE_PEAKS = 'Three Peaks';
    case STRIPE_CONNECT = 'Stripe Connect';
}
