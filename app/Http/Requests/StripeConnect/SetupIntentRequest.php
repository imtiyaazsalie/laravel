<?php

namespace App\Http\Requests\StripeConnect;

use App\Enums\StripeConnect\StripeConnectPaymentMethodType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetupIntentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'location_id' => [Rule::requiredIf(! in_array('card', $this->input('payment_methods'))), 'integer', 'exists:box_facility,box_facility_id'],
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'payment_methods' => ['required', 'array'],
            'payment_methods.*' => [Rule::enum(StripeConnectPaymentMethodType::class)],
        ];
    }
}
