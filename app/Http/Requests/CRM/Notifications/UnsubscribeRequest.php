<?php

namespace App\Http\Requests\CRM\Notifications;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UnsubscribeRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('notification')->tenant_id,
            allowMember: true,
            userId: $this->user_id,
            userTypes: UserType::tenantAdmins(),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,user_id',
        ];
    }
}
