<?php

namespace App\Enums\StripeConnect;

enum StripeConnectPaymentMethodType: string
{
    case ACH_DEBIT = 'ach_debit';
    case JCB = 'jcb';
    case APPLE_PAY = 'apple_pay';
    case GOOGLE_PAY = 'google_pay';

    case ACSS_DEBIT = 'acss_debit';

    case AFFIRM = 'affirm';

    case AFTERPAY_CLEARPAY = 'afterpay_clearpay';

    case ALIPAY = 'alipay';

    case AU_BECS_DEBIT = 'au_becs_debit';

    case BACS_DEBIT = 'bacs_debit';

    case BANCONTACT = 'bancontact';

    case BLIK = 'blik';

    case BOLETO = 'boleto';

    case CARD = 'card';

    case CARD_PRESENT = 'card_present';

    case CASHAPP = 'cashapp';

    case CUSTOMER_BALANCE = 'customer_balance';

    case EPS = 'eps';

    case FPX = 'fpx';

    case GIROPAY = 'giropay';

    case GRABPAY = 'grabpay';

    case IDEAL = 'ideal';

    case INTERAC_PRESENT = 'interac_present';

    case KLARNA = 'klarna';

    case KONBINI = 'konbini';

    case LINK = 'link';

    case OXXO = 'oxxo';

    case P24 = 'p24';

    case PAYNOW = 'paynow';

    case PAYPAL = 'paypal';

    case PIX = 'pix';

    case PROMPTPAY = 'promptpay';

    case SEPA_DEBIT = 'sepa_debit';

    case SOFORT = 'sofort';

    case US_BANK_ACCOUNT = 'us_bank_account';

    case WECHAT_PAY = 'wechat_pay';

    case ZIP = 'zip';

    public function getDescription(): string
    {
        return match ($this) {
            self::ACH_DEBIT => 'ACH Debit',
            self::JCB => 'JCB Credit Card',
            self::APPLE_PAY => 'Apple Pay',
            self::GOOGLE_PAY => 'Google Pay',
            self::ACSS_DEBIT => 'ACSS Debit',
            self::AFFIRM => 'Affirm',
            self::AFTERPAY_CLEARPAY => 'Afterpay / Clearpay',
            self::ALIPAY => 'Alipay',
            self::AU_BECS_DEBIT => 'AU BECS Debit',
            self::BACS_DEBIT => 'BACS Debit',
            self::BANCONTACT => 'Bancontact',
            self::BLIK => 'BLIK',
            self::BOLETO => 'Boleto',
            self::CARD => 'Credit/Debit Card',
            self::CARD_PRESENT => 'Card Present',
            self::CASHAPP => 'Cash App',
            self::CUSTOMER_BALANCE => 'Customer Balance',
            self::EPS => 'EPS',
            self::FPX => 'FPX',
            self::GIROPAY => 'Giropay',
            self::GRABPAY => 'GrabPay',
            self::IDEAL => 'iDEAL',
            self::INTERAC_PRESENT => 'Interac Present',
            self::KLARNA => 'Klarna',
            self::KONBINI => 'Konbini',
            self::LINK => 'Link',
            self::OXXO => 'OXXO',
            self::P24 => 'P24',
            self::PAYNOW => 'PayNow',
            self::PAYPAL => 'PayPal',
            self::PIX => 'PIX',
            self::PROMPTPAY => 'PromptPay',
            self::SEPA_DEBIT => 'SEPA Debit',
            self::SOFORT => 'Sofort',
            self::US_BANK_ACCOUNT => 'US Bank Account',
            self::WECHAT_PAY => 'WeChat Pay',
            self::ZIP => 'Zip',
        };
    }
}
