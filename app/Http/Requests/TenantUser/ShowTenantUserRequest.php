<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ShowTenantUserRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: [
                ...UserType::tenantStaff(),
                UserType::SUPER_ADMINISTRATOR,
                UserType::ADMIN,
            ],
            tenantId: $this->route('userTenant')->tenant_id,
            allowMember: true,
            userId: $this->route('userTenant')->user_id,
            userIdStatuses: UserStatus::allStatuses()
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
