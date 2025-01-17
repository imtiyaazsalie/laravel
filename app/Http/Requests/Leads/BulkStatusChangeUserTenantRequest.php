<?php

namespace App\Http\Requests\Leads;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class BulkStatusChangeUserTenantRequest extends FormRequest
{
    use Authorize;

    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantAdmins(),
            tenantId: $this->input('tenant_id')
        );
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'lead_member_ids' => 'required|array',
            'lead_member_ids.*' => 'integer|exists:lead_members,member_id',
            'status' => 'required|in:pending,drop_in,contacted,follow_up,request_demo,other',
        ];
    }
}
