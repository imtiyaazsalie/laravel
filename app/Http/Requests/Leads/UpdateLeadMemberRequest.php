<?php

namespace App\Http\Requests\Leads;

use App\Enums\Gender;
use App\Enums\LeadMemberStatus;
use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateLeadMemberRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->input('tenant_id'),
            locationId: $this->input('location_id')
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
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'status' => ['required', new Enum(LeadMemberStatus::class)],
            'source' => 'nullable|string',
            'name' => 'required|string|min:1|max:120',
            'surname' => 'required|string|min:1|max:120',
            'date_of_birth' => 'nullable|date|before:today',
            'gender_id' => ['nullable', new Enum(Gender::class)],
            'last_contacted_at' => 'nullable|date',
            'next_follow_up_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'referred_by_id' => 'nullable|integer|exists:users,user_id',
        ];
    }
}
