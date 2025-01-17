<?php

namespace App\Http\Requests\WodCapture\Exercises;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class RejectWodCaptureExerciseRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                UserType::HEAD_COACH,
                UserType::GYM_COACH,
                UserType::BOX_ADMIN,
            ],
            tenantId: $this->route('wodCaptureExercise')->capture->wod->tenant_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
