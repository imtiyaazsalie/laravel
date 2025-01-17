<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadMemberStatus;
use App\Enums\LeadMemberType;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListLeadMembersRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: $this->input('filter.tenant_id'),
            locationId: $this->input('filter.location_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'filter.status' => 'sometimes|string|in:'.implode(',', LeadMemberStatus::values()),
            'filter.type' => 'sometimes|string|in:'.implode(',', LeadMemberType::values()),
            'filter.is_redacted' => ['sometimes', 'nullable', new BooleanRule()],
            'filter.search' => 'sometimes|string',
        ];
    }
}
