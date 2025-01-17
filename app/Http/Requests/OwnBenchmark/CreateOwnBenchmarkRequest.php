<?php

namespace App\Http\Requests\OwnBenchmark;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\ScoreRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateOwnBenchmarkRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            allowMember: true,
            userId: $this->user_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'exercise_id' => ['nullable', 'integer', 'exists:exercise,exercise_id'],
            'is_rx' => ['required', new BooleanRule()],
            'score' => ['required', new ScoreRule],
            'date' => 'required|date',
            'note' => 'nullable|string',
            'is_consider_for_leader_board' => [new BooleanRule()],
        ];
    }
}
