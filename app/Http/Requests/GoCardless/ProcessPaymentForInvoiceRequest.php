<?php

namespace App\Http\Requests\GoCardless;

use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentForInvoiceRequest extends FormRequest
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
            'invoice_id' => 'required|exists:finance_invoices,invoice_id',
            'settings_id' => 'required|exists:facility_payment_gateway_settings,setting_id',
        ];
    }
}
