<?php

namespace App\Http\Requests\Tenants;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class TenantUpdateRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string',
            'description' => 'nullable|string|max:255',
            'region_id' => 'sometimes|integer|exists:regions,region_id',
            'is_trial' => ['sometimes', new BooleanRule()],
            'timezone_id' => 'sometimes|integer|exists:timezones,timezone_id',
            'tenant_billing_currency_id' => 'sometimes|integer|exists:currencies,currency_id',
            'member_billing_currency_id' => 'sometimes|integer|exists:currencies,currency_id',
            'status' => 'sometimes|integer',
            'website_url' => ['nullable', 'url'],
            'instagram_url' => ['nullable', 'url'],
            'facebook_url' => ['nullable', 'url'],
        ];
    }
}
