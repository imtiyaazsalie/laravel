<?php

namespace App\Http\Requests\CRM\Notifications;

use App\Enums\NotificationStatus;
use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateNotificationRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('notification')?->tenant_id ?? $this->input('tenant_id'),
            userTypes: [
                UserType::HEAD_COACH,
                UserType::BOX_ADMIN,
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'subject' => 'required|string|min:5|max:254',
            'content' => 'required|string|min:20',
            'status' => ['required', new Enum(NotificationStatus::class)],
            'cc' => 'nullable|email:rfc,dns',
        ];
    }
}
