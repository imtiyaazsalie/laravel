<?php

namespace App\Http\Requests\GoCardless;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class SendOnboardingLinkRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: $this->tenant_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|exists:boxes,box_id',
            'user_ids' => 'required|array|exists:users,user_id',
        ];
    }
}
