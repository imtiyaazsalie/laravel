<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ShowTenantContractSettingsRequest extends FormRequest
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
            userTypes: UserType::allRoles(),
            allowMember: true
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
