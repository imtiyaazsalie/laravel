<?php

namespace App\Http\Requests\WodCapture\Likes;

use App\Enums\UserType;
use App\Models\WodCapture;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrDeleteWodCaptureLikeRequest extends FormRequest
{
    use Authorize;

    public function authorize(): Response|bool
    {
        $wodCapture = WodCapture::findOrFail($this->wod_capture_id);

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
            'wod_capture_id' => 'required|integer|exists:wod_capture,wod_capture_id',
        ];
    }
}
