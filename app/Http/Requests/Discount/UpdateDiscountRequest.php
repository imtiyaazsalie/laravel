<?php

namespace App\Http\Requests\Discount;

use App\Enums\UserType;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscountRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            permission: 'settings',
            tenantId: $this->route('financeDiscount')->tenant_id,
            userTypes: [
                UserType::HEAD_COACH,
                UserType::BOX_FACILITY_ADMIN,
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'description' => 'required|string|min:3|max:255',
            'type' => ['required', 'in:percentage,fixed'],
            'amount' => ['required', new PriceRule()],
        ];
    }
}
