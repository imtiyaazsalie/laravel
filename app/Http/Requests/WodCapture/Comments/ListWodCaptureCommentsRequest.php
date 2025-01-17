<?php

namespace App\Http\Requests\WodCapture\Comments;

use App\Enums\UserType;
use App\Models\WodCapture;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListWodCaptureCommentsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        //TODO: Refine for multiple IDs?
        $wodCapture = WodCapture::findOrFail($this->input('filter.wod_capture_id'));

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $wodCapture->wod->tenant_id,
            allowMember: true,
            userId: auth()->user()->getAuthIdentifier()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.wod_capture_id' => ['required', 'integer', 'exists:wod_capture,wod_capture_id'],
            'per_page' => new PerPageRule(),
        ];
    }
}
