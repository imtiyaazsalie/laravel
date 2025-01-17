<?php

namespace App\Http\Requests\Paystack;

use App\Enums\PaymentGateway;
use App\Rules\FinancePaymentGatewaySettingRule;
use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class InitializePaystackTransactionRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:finance_invoices,invoice_id'],
            'settings_id' => ['required', new FinancePaymentGatewaySettingRule(paymentGateway: PaymentGateway::PAYSTACK->value, checkValidity: true)],
        ];
    }
}
