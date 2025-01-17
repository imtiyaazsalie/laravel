<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteTenantUserRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                ...UserType::locationAdmins(),
                UserType::SUPER_ADMINISTRATOR,
            ],
            permission: 'member_actions',
            tenantId: $this->input('tenant_id'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'user_tenant_ids' => ['required', 'array'],
            'user_tenant_ids.*' => ['integer'],
        ];
    }
}
