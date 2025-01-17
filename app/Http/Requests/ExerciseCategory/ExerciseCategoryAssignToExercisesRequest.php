<?php

namespace App\Http\Requests\ExerciseCategory;

use App\Enums\UserType;
use App\Rules\ExerciseRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ExerciseCategoryAssignToExercisesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'benchmark_exercise_ids' => ['required', 'array', new ExerciseRule()],
        ];
    }
}
