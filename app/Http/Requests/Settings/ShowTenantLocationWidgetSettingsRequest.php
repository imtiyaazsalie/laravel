<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ShowTenantLocationWidgetSettingsRequest extends FormRequest
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
                UserType::BOX_ADMIN,
            ],
            permission: 'settings',
            tenantId: $this->route('tenant') ? $this->route('tenant')?->getKey() : $this->route('location')->tenant_id,
            locationId: $this->location_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
        ];
    }
}
