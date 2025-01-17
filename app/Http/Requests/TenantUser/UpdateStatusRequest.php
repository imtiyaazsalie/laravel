<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Rules\EnumRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
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
                UserType::ADMIN,
            ],
            tenantId: $this->tenant_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,user_id'],
            'status_id' => ['required', new EnumRule(UserStatus::class)],
        ];
    }
}
