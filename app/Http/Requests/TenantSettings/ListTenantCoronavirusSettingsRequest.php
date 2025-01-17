<?php

namespace App\Http\Requests\TenantSettings;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListTenantCoronavirusSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                ...UserType::tenantUsers(),
                UserType::LOCATION_CHECK_IN,
            ],
            tenantId: $this->route('tenant')->getKey(),
            allowMember: true,
            allowLocationCheckIn: true
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
