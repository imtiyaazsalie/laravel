<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Rules\EnumRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListUserTenantsFinancesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->input('filter.tenant_id'),
            locationId: $this->input('filter.location_id'),
            userTypes: [
                ...UserType::tenantStaff(),
                UserType::SUPER_ADMINISTRATOR,
                UserType::ADMIN,
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'filter.type_id' => ['nullable', new EnumRule(UserType::class)],
            'filter.status_id' => ['nullable', new EnumRule(UserStatus::class)],
            'filter.programme_id' => ['nullable', 'integer', 'exists:programmes,id'],
            'filter.debit_status_id' => ['nullable', new EnumRule(UserDebitStatus::class)],
            'filter.user_id' => 'sometimes|integer|exists:users,user_id',
            'filter.search' => 'nullable|string',
            'page' => 'integer',
            'per_page' => new PerPageRule,
        ];
    }
}
