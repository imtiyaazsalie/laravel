<?php

namespace App\Http\Requests\WodCapture\Comments;

use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class StoreWodCaptureCommentRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'wod_capture_id' => ['required', 'integer', 'exists:wod_capture,wod_capture_id'],
            'content' => 'required|string',
        ];
    }
}
