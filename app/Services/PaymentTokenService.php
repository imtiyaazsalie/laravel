<?php

namespace App\Services;

use App\Enums\FinancePaymentTokenType;
use App\Enums\PaymentGateway;
use App\Models\FinancePaymentToken;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentTokenService
{
    public function getFinancePaymentToken(array $paymentMethods, FinancePaymentTokenType $financePaymentTokenType, User|int|null $user = null, Location|int|null $location = null, ?PaymentGateway $paymentGateway = null): Model|Builder|null
    {
        if (! $user && ! $location) {
            return null;
        }

        if ($financePaymentTokenType === FinancePaymentTokenType::LOCATION) {
            $user = null;
        }

        return FinancePaymentToken::query()
            ->whereIn('payment_method', $paymentMethods)
            ->where('type', '=', $financePaymentTokenType)
            ->when($user, function (Builder $builder) use ($user) {
                $builder->where('user_id', $user instanceof User ? $user->getKey() : $user);
            })
            ->when($location, function (Builder $builder) use ($location) {
                $builder->where('location_id', $location instanceof Location ? $location->getKey() : $location);
            })
            ->when($paymentGateway, function (Builder $builder) use ($paymentGateway) {
                $builder->where('payment_gateway_id', $paymentGateway);

                $builder->when($paymentGateway === PaymentGateway::STRIPE_CONNECT, function (Builder $builder) {
                    $builder->whereNotNull('customer_id');
                });
            })
            ->first();
    }
}
