<?php

namespace App\Http\Requests\User;

use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Rules\DatesBetweenRule;
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
            userTypes: UserType::locationAdmins(),
            tenantId: $this->input('filter.tenant_id'),
            locationId: $this->input('filter.location_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.user_id' => 'sometimes|integer|exists:users,user_id',
            'filter.search' => 'nullable|string',
            'filter.user_tenant_location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'filter.user_tenant_type_id' => ['nullable', new EnumRule(UserType::class)],
            'filter.user_tenant_status_id' => ['nullable', new EnumRule(UserStatus::class)],
            'filter.user_tenant_debit_status_id' => ['nullable', new EnumRule(UserDebitStatus::class)],
            'filter.user_tenant_programme_id' => ['nullable', 'integer', 'exists:programmes,id'],
            'filter.user_tenant_assigned_user_id' => 'nullable|integer|exists:users,user_id',
            'filter.birthdays_between' => ['nullable', new DatesBetweenRule],
        ];
    }
}
