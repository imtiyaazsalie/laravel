<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantContractSettingsRequest extends FormRequest
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
            'contract_expiry_notification_days' => 'required|integer',
            'contract_terms_and_conditions' => 'required|string',
            'is_user_contract_visible_in_app' => ['required', new BooleanRule()],
        ];
    }
}
