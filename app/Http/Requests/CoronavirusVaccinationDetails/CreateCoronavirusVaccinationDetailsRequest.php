<?php

namespace App\Http\Requests\CoronavirusVaccinationDetails;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateCoronavirusVaccinationDetailsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->tenant_id,
            userId: $this->user_id,
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'user_id' => ['required', 'integer', 'exists:users,user_id', 'unique:coronavirus_vaccination_details,user_id,box_id'],
            'status' => 'required|in:unvaccinated,vaccinated,boosted',
            'file' => 'nullable|file|max:4000|mimes:jpg,png,pdf',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'Vaccination status already exists for this user.',
        ];
    }
}
