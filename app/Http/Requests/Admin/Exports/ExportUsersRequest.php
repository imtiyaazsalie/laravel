<?php

namespace App\Http\Requests\Admin\Exports;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\EnumRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ExportUsersRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'sometimes|exists:boxes,box_id',
            'filter.location_id' => 'sometimes|exists:box_facility,box_facility_id',
            'filter.region_id' => 'sometimes|exists:regions,region_id',
            'filter.location_active' => new BooleanRule,
            'filter.tenant_status_id' => ['sometimes', new EnumRule(TenantStatus::class)],
            'filter.user_status_id' => ['sometimes', new EnumRule(UserStatus::class)],
            'filter.user_group' => ['required', 'in:all,staff,member'],
        ];
    }
}
