<?php

namespace App\Http\Requests\PersonalWod;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\MeasurementUnitRule;
use App\Rules\ScoreRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonalWodRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('personalWod')->tenant_id,
            allowMember: true,
            userId: $this->route('personalWod')->user_id,
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:254',
            'description' => 'nullable|string',
            'is_rx' => ['required', new BooleanRule()],
            'date' => 'required|date',
            'score' => ['required', new ScoreRule],
            'note' => 'nullable|string',
            'measuring_unit_id' => new MeasurementUnitRule,
        ];
    }
}
