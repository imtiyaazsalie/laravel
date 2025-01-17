<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRegistrationSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            permission: 'settings',
            tenantId: $this->route('tenant')->tenant_id,
            userTypes: UserType::locationAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'sign_up_payment_options' => 'required',
            'sign_up_payment_options.*' => 'required|between:1,7',

            'sign_up_debit_day_options' => 'required|array',
            'sign_up_debit_day_options.*' => 'required|between:1,7',

            'is_sign_up_use_contracts_and_waivers' => ['required', new BooleanRule()],
            'sign_up_required_fields' => 'sometimes|array',
            'sign_up_required_fields.*' => 'sometimes|in:address,id_number',
            'pro_rate_strategy' => 'required|string|in:manual,automatic',
        ];
    }
}
