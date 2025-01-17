<?php

namespace App\Http\Requests;

use App\Enums\StripeConnect\StripeConnectPaymentMethodType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetPaymentMethodsRequest extends FormRequest
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
            'filter.user_id' => 'required|integer|exists:users,user_id',
            'filter.location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'filter.type' => ['sometimes', 'string', Rule::enum(StripeConnectPaymentMethodType::class)],
        ];
    }
}
