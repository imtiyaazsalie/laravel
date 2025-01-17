<?php

namespace App\Models;

use App\Enums\PaymentGateway as EnumsPaymentGateway;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $table = 'payment_gateways';

    protected $primaryKey = 'payment_gateway_id';

    public $timestamps = false;

    /**
     * Mutate payment_gateway_name to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_gateway_name,
            set: fn (mixed $value) => ['payment_gateway_name' => $value]
        );
    }

    public function isGoCardless(): bool
    {
        return $this->payment_gateway_id === EnumsPaymentGateway::GO_CARDLESS->value;
    }

    public function isStripe(): bool
    {
        return $this->payment_gateway_id === EnumsPaymentGateway::STRIPE->value;
    }

    public function isNetcash(): bool
    {
        return $this->payment_gateway_id === EnumsPaymentGateway::SAGE_PAY_V3->value;
    }

    public function isThreePeaks(): bool
    {
        return $this->payment_gateway_id === EnumsPaymentGateway::THREE_PEAKS->value;
    }

    public function isNoGateway(): bool
    {
        return $this->payment_gateway_id === EnumsPaymentGateway::NO_GATEWAY->value;
    }

    public function isPaystack(): bool
    {
        return $this->payment_gateway_id === EnumsPaymentGateway::PAYSTACK->value;
    }

    public function isSepa(): bool
    {
        return $this->payment_gateway_id === EnumsPaymentGateway::SEPA->value;
    }

    public function isStripeConnect(): bool
    {
        return $this->payment_gateway_id === EnumsPaymentGateway::STRIPE_CONNECT->value;
    }
}
