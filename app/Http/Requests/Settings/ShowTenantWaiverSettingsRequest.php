<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ShowTenantWaiverSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->canOperate(
            tenantId: $tenant->getKey(),
            allowMember: true,
            userTypes: [
                UserType::HEAD_COACH,
                UserType::BOX_ADMIN,
                UserType::BOX_FACILITY_ADMIN,
                UserType::GYM_MEMBER,
            ]
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
