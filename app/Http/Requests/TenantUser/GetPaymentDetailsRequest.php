<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class GetPaymentDetailsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                UserType::SUPER_ADMINISTRATOR,
                UserType::ADMIN,
                ...UserType::tenantUsers(),
            ],
            permission: 'member_manage_package',
            tenantId: $this->route('userTenant')->tenant_id,
            allowMember: true,
            userId: $this->route('userTenant')->user_id
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
