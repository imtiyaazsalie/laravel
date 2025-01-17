<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum PaymentGateway: int
{
    use SmartEnum;

    case NO_GATEWAY = 1;
    case SAGE_PAY_V2 = 2;
    case THREE_PEAKS = 3;
    case SAGE_PAY_V3 = 4;
    case GO_CARDLESS = 5;
    case STRIPE = 6;
    case PAYSTACK = 7;
    case SEPA = 8;
    case STRIPE_CONNECT = 9;
    case PAYFAST = 10;

    public static function getLocationPaymentGatewayDiscr($paymentGateway): string
    {
        return match ($paymentGateway) {
            self::THREE_PEAKS => 'three_peaks',
            self::SAGE_PAY_V3 => 'sage',
            self::GO_CARDLESS => 'go_cardless',
            self::STRIPE => 'stripe',
            self::PAYSTACK => 'paystack',
            self::SEPA => 'sepa',
            self::STRIPE_CONNECT => 'stripe_connect',
            self::PAYFAST => 'payfast',
        };
    }
}
