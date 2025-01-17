<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateUserTenantRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                ...UserType::tenantStaff(),
                UserType::SUPER_ADMINISTRATOR,
                UserType::GYM_MEMBER,
            ],
            tenantId: $this->route('userTenant')->box_id,
            allowMember: true,
            userId: $this->route('userTenant')->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type_id' => ['nullable', new Enum(UserType::class)],
            'programme_id' => ['nullable', 'exists:programmes,id'],
            'member_id' => ['nullable'],
            'bio' => ['nullable'],
            'notes' => ['nullable'],
            'assigned_user_id' => ['nullable', 'exists:users,user_id'],
            'high_risk' => ['nullable', new BooleanRule],
            'location_id' => ['nullable', 'exists:box_facility,box_facility_id'],
            'default_location_id' => ['nullable', 'exists:box_facility,box_facility_id'],
            'region_id' => ['nullable', 'exists:regions,region_id'],
            'landing_screen' => ['nullable'],
            'location_access_privileges' => ['nullable', 'array'],
            'location_access_privileges.*' => ['integer', 'exists:box_facility,box_facility_id'],
            'user_access_privileges' => ['nullable', 'array'],
            'user_access_privileges.*' => ['integer', 'exists:access_privileges,access_privilege_id'],
        ];
    }
}
