<?php

namespace App\Http\Requests\CRM\Settings;

use App\Enums\UserType;
use App\Models\CrmSetting;
use App\Rules\ImageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        /** @var CrmSetting $settings */
        $settings = $this->route('setting');

        $tenantId = $settings->tenant_id ?: $settings->location?->tenant_id;

        if (! $tenantId) {
            return false;
        }

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $tenantId,
            locationId: $settings->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // 'location_id' => 'required|exists:box_facility,box_facility_id',
            'sms_status' => 'required|in:enabled,disabled',
            'email_status' => 'required|in:enabled,disabled',
            'email_signature' => 'required|string',
            'reply_to' => 'nullable|email:rfc,dns',
            'sender_name' => 'nullable|string|min:3|max:254',
            'logo' => ['nullable', new ImageRule()],
        ];
    }
}
