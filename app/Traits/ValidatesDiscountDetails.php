<?php

namespace App\Traits;

use App\Rules\DiscountRule;
use App\Rules\PriceRule;

trait ValidatesDiscountDetails
{
    public function discountDetailsValidationRules(): array
    {
        return [
            'discount_details' => 'nullable|array',

            'discount_details.discount_type' => [
                'required_with:discount_details.amount,discount_details.discount_id|in:specialRate,discount',
            ],

            'discount_details.amount' => [
                'required_if:discount_details.discount_type,specialRate|integer|min:1',
                new PriceRule,
            ],

            'discount_details.discount_id' => [
                'required_if:discount_details.discount_type,discount_id|exists:finance_discounts,discount_id',
                'integer',
                new DiscountRule(),
            ],

        ];
    }
}
