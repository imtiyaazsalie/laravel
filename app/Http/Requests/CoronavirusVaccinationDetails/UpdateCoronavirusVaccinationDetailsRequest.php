<?php

namespace App\Http\Requests\CoronavirusVaccinationDetails;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCoronavirusVaccinationDetailsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('result')->tenant_id,
            userId: $this->route('result')->user_id,
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:unvaccinated,vaccinated,boosted',
            'file' => 'nullable|file|max:4000|mimes:jpg,png,pdf',
        ];
    }
}
