<?php

namespace App\Http\Requests\Leads;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConvertLeadMemberRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $leadMember = $this->route('leadMember');

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $leadMember->location->tenant_id,
            userId: $leadMember->user_id
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
            'location_id' => 'required|exists:box_facility,box_facility_id',
        ];
    }
}
