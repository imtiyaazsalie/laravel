<?php

namespace App\Http\Requests\WodCapture\Likes;

use App\Enums\UserType;
use App\Models\WodCapture;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListWodCaptureLikesRequest extends FormRequest
{
    use Authorize;

    public function authorize(): Response|bool
    {
        //TODO: Refine for multiple IDs?
        $wodCaptureId = Arr::get($this->filter, 'wod_capture_id');

        $wodCapture = WodCapture::findOrFail($wodCaptureId);

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $wodCapture->wod->tenant_id,
            allowMember: true,
            userId: auth()->user()->getAuthIdentifier()
        );
    }

    public function rules(): array
    {
        return [
            'filter.wod_capture_id' => ['required', 'integer', 'exists:wod_capture,wod_capture_id'],
            'per_page' => new PerPageRule(),
        ];
    }
}
