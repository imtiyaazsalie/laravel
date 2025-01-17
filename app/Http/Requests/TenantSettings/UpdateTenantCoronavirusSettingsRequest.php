<?php

namespace App\Http\Requests\TenantSettings;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantCoronavirusSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('tenant')->getKey(),
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
            'is_enabled' => ['required', new BooleanRule()],
            'is_enabled_questionnaire_member_app' => ['required', new BooleanRule()],
            'is_display_vaccination_details_roster' => ['required', new BooleanRule()],
            'is_display_vaccination_details_class' => ['required', new BooleanRule()],
        ];
    }
}
