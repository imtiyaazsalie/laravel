<?php

namespace App\Http\Requests\WodCapture\Exercises;

use App\Enums\UserType;
use App\Models\Wod;
use App\Rules\BooleanRule;
use App\Rules\ExerciseRule;
use App\Rules\ScoreRule;
use App\Rules\UniqueWodCaptureExerciseRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class StoreWodCaptureExerciseRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $wod = Wod::findOrFail($this->wod_id);

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $wod->tenant_id,
            allowMember: true,
            userId: $this->user_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $wod = Wod::findOrFail($this->wod_id);

        return [
            'wod_id' => 'required|integer|exists:wods,wod_id',
            'user_id' => 'integer|integer|exists:users,user_id',
            'exercise_id' => ['required', new ExerciseRule($wod->tenant_id), new UniqueWodCaptureExerciseRule()],
            'score' => ['required', new ScoreRule],
            'is_rx' => ['required', new BooleanRule()],
            'note' => 'nullable|string',
        ];
    }
}
