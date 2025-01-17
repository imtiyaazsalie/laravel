<?php

namespace App\Http\Requests\CoronavirusQuestionaireResults;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCoronavirusQuestionaireResultRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::AllRoles(),
            tenantId: $this->route('result')->classBooking->class->tenant_id,
            allowMember: true,
            userId: $this->route('result')->classBooking->user_id
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
