<?php

namespace App\Http\Requests\Waivers;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class DownloadWaiverRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->tenant_id,
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
            'tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
        ];
    }
}
