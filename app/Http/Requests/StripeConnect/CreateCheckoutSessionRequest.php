<?php

namespace App\Http\Requests\StripeConnect;

use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutSessionRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
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
            'settings_id' => ['required', 'integer', 'exists:facility_payment_gateway_settings,setting_id'],
        ];
    }
}
