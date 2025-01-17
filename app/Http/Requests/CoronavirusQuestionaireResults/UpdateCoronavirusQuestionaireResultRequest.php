<?php

namespace App\Http\Requests\CoronavirusQuestionaireResults;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCoronavirusQuestionaireResultRequest extends FormRequest
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
        return [
            'temperature' => 'required|string|min:1|max:5',
            'has_cough' => ['nullable', new BooleanRule()],
            'has_difficulty_breathing' => ['nullable', new BooleanRule()],
            'has_fever' => ['nullable', new BooleanRule()],
            'has_been_in_contact_experiencing' => ['nullable', new BooleanRule()],
            'has_been_in_contact_positive' => ['nullable', new BooleanRule()],
            'has_travelled' => ['nullable', new BooleanRule()],
            'travelled_where' => 'nullable|string|min:2|max:120',
        ];
    }
}
