<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\TenantUser;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class DeleteTenantUserRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        /** @var TenantUser $tenantUser */
        $tenantUser = $this->route('userTenant');

        return $this->canOperate(
            userTypes: [
                ...UserType::locationAdmins(),
                UserType::SUPER_ADMINISTRATOR,
            ],
            permission: $tenantUser->isMember() ? 'member_actions' : null,
            tenantId: $tenantUser->tenant_id,
            userId: $tenantUser->user_id,
            userIdStatuses: UserStatus::allStatuses(),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
        ];
    }
}
