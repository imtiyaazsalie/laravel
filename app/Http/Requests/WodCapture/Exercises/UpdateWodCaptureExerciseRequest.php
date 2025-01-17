<?php

namespace App\Http\Requests\WodCapture\Exercises;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\ScoreRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWodCaptureExerciseRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->route('wodCaptureExercise')->capture->wod->tenant_id,
            allowMember: true,
            userId: $this->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'score' => ['required', new ScoreRule],
            'is_rx' => ['required', new BooleanRule()],
            'note' => 'nullable|string',
        ];
    }
}
