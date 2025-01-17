<?php

namespace App\Traits;

use App\Enums\InvoicePaymentType;
use App\Enums\UserDebitStatus;
use App\Rules\DebitDayRule;
use App\Rules\EnumRule;
use App\Rules\IbanRule;
use App\Rules\PriceRule;
use Illuminate\Validation\Rules\Enum;

trait ValidatesPaymentDetails
{
    public function paymentDetailsValidationRules(bool $validateAccountDetails, bool $validateSepaDetails = false, bool $nullable = false): array
    {
        $rules = [
            'payment_details' => $nullable ? 'nullable|array' : 'required|array',
            'payment_details.debit_status_id' => ['required', new Enum(UserDebitStatus::class)],
            'payment_details.invoicing_type' => [
                'required_if:payment_details.debit_status_id,'.UserDebitStatus::CASH->value,
                'string',
            ],
            'payment_details.auto_invoicing_day' => [
                'required_if:payment_details.invoicing_type,customDates',
            ],
            'payment_details.auto_invoicing_due_day' => [
                'required_with:payment_details.auto_invoicing_day',
            ],
            'payment_details.debit_day_id' => [
                'required_if:payment_details.debit_status_id,'.UserDebitStatus::DEBIT_ORDER->value,
                new DebitDayRule(),
            ],
            'payment_details.upfront_payment_method' => [
                'required_if:payment_details.debit_status_id,'.UserDebitStatus::UP_FRONT_PAYMENT->value,
                'string',
                new EnumRule(InvoicePaymentType::class),
            ],
            'payment_details.upfront_payment_amount' => [
                'required_with:payment_details.upfront_payment_method',
                new PriceRule,
            ],
            'payment_details.upfront_payment_period' => [
                'required_with:payment_details.upfront_payment_method',
                'numeric',
                'gt:0',
            ],
            'payment_details.upfront_payment_period_type' => [
                'required_with:payment_details.upfront_payment_method',
                'string',
                'in:days,weeks,months,years',
            ],
            'payment_details.file' => [
                'nullable',
                'file',
                'max:4000',
            ],
        ];

        if ($validateAccountDetails) {
            $rules = array_merge($rules, [
                'payment_details.bank_id' => ['required'],
                'payment_details.account_type_id' => 'required|exists:account_types,account_type_id',
                'payment_details.account_number' => 'required',
                'payment_details.branch_code' => 'nullable',
            ]);
        }

        if ($validateSepaDetails) {
            $rules = array_merge($rules, [
                'payment_details.account_holder_name' => 'required',
                'payment_details.bic' => 'required',
                'payment_details.iban' => ['required', new IbanRule($this->input('payment_details.bic'))],
                'payment_details.address' => 'sometimes',
            ]);
        }

        return $rules;
    }
}
