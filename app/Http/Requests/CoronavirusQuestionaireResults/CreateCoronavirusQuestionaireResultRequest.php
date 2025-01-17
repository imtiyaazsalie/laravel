<?php

namespace App\Http\Requests\CoronavirusQuestionaireResults;

use App\Enums\UserType;
use App\Models\ClassBooking;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateCoronavirusQuestionaireResultRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $classBooking = ClassBooking::findOrFail($this->class_booking_id);

        return $this->canOperate(
            userTypes: UserType::AllRoles(),
            tenantId: $classBooking->class->tenant_id,
            locationId: $classBooking->class->location_id,
            allowMember: true,
            userId: $classBooking->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'class_booking_id' => ['required', 'integer', 'exists:class_bookings,class_booking_id', 'unique:covid19_questionnaire_results,class_booking_id'],
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
