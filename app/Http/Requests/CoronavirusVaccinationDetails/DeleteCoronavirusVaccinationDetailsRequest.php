<?php

namespace App\Http\Requests\CoronavirusVaccinationDetails;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCoronavirusVaccinationDetailsRequest extends FormRequest
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
            userTypes: [
                UserType::HEAD_COACH,
                UserType::BOX_ADMIN,
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
