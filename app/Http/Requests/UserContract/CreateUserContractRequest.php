<?php

namespace App\Http\Requests\UserContract;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserContractRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->tenant_id,
            locationId: $this->location_id,
            userId: $this->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'user_id' => 'required|integer|exists:users,user_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'file' => 'nullable|mimes:pdf,doc,docx,jpeg,webp|max:4000',
        ];
    }
}
