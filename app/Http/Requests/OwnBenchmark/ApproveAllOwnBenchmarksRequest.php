<?php

namespace App\Http\Requests\OwnBenchmark;

use App\Enums\UserType;
use App\Rules\ExerciseRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ApproveAllOwnBenchmarksRequest extends FormRequest
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
            userTypes: UserType::locationAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'exercise_id' => ['nullable', new ExerciseRule($this->tenant_id)],
        ];
    }
}
