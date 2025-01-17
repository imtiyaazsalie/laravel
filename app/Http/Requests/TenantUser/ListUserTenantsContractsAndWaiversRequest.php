<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Rules\EnumRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUserTenantsContractsAndWaiversRequest extends FormRequest
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

    public function messages(): array
    {
        return [
            'filter.start_date.required' => 'Start date is required if the date_to_query field is set and the end date field is null.',
            'filter.end_date.required' => 'End date is required if the date_to_query field is set and the start date field is null.',
        ];
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
            'filter.date_to_query' => ['nullable', 'string', 'in:starting_on,ending_on,created_on'],
            'filter.start_date' => [Rule::requiredIf(fn () => $this->input('filter.date_to_query') && ! $this->input('filter.end_date')), 'nullable', 'date'],
            'filter.end_date' => [Rule::requiredIf(fn () => $this->input('filter.date_to_query') && ! $this->input('filter.start_date')), 'nullable', 'date', 'after_or_equal:filter.start_date'],
            'filter.user_id' => 'sometimes|integer|exists:users,user_id',
            'filter.search' => 'nullable|string',
            'page' => 'integer',
            'per_page' => new PerPageRule,
        ];
    }
}
