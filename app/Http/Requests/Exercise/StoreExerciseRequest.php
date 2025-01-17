<?php

namespace App\Http\Requests\Exercise;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                ...UserType::tenantAdmins(),
                UserType::SUPER_ADMINISTRATOR,
            ],
            tenantId: $this->tenant_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => [auth()->user()->isAdmin() ? 'nullable' : 'required', 'integer', 'exists:boxes,box_id'],
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string',
            'resource_url' => 'nullable|active_url',
            'measuring_unit_id' => 'required|exists:measuring_units,measuring_unit_id',
            'rx_male' => 'nullable|string',
            'rx_female' => 'nullable|string',
            'exercise_category_id' => 'nullable|exists:exercise_category,exercise_category_id',
            'is_benchmark' => ['required', new BooleanRule()],
        ];
    }
}
