<?php

namespace App\Http\Requests\TenantSettings;

use App\Enums\UserType;
use App\Rules\ImageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->route('tenant')->getKey()
        );

    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'theme' => 'nullable|array',
            'hidden_features' => 'nullable|array',
            'logo' => ['nullable', new ImageRule()],
            'locale_language' => 'sometimes|in:en,fr,de',
        ];
    }
}
