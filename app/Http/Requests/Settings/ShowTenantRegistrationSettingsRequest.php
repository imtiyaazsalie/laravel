<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ShowTenantRegistrationSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('tenant')->tenant_id,
            userTypes: [
                UserType::HEAD_COACH,
                UserType::BOX_ADMIN,
                UserType::BOX_FACILITY_ADMIN,
                UserType::GYM_MEMBER,
            ],
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
