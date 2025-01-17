<?php

namespace App\Http\Requests\WodCapture\Exercises;

use App\Enums\UserType;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListWodCaptureExercisesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->input('filter.tenant_id'),
            allowMember: true,
            userId: $this->input('filter.user_id'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.user_id' => 'integer|exists:users,user_id',
            'filter.wod_id' => ['nullable', 'integer', 'exists:wods,wod_id'],
            'filter.exercise_id' => ['nullable', 'integer', 'exists:exercise,exercise_id'],
            'per_page' => new PerPageRule,
        ];
    }
}
