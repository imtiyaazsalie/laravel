<?php

namespace App\Http\Requests\Tenants;

use App\Enums\Gender;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\DateOfBirthRule;
use App\Rules\EnumRule;
use App\Rules\MobileNumberRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class TenantCreateRequest extends FormRequest
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
            'name' => 'required|string',
            'description' => 'nullable|string|max:255',
            'region_id' => 'required|integer|exists:regions,region_id',
            'is_trial' => ['required', new BooleanRule()],
            'timezone_id' => 'required|exists:timezones,timezone_id',
            'billing_currency_id' => 'required|integer|exists:currencies,currency_id',
            'member_billing_currency_id' => 'required|integer|exists:currencies,currency_id',
            'user_name' => 'required|string',
            'user_surname' => 'required|string',
            'user_gender_id' => ['required', new EnumRule(Gender::class)],
            'user_email' => 'required|email',
            'user_mobile' => ['required', new MobileNumberRule()],
            'user_date_of_birth' => ['required', new DateOfBirthRule()],
            'website_url' => ['nullable', 'url'],
            'instagram_url' => ['nullable', 'url'],
            'facebook_url' => ['nullable', 'url'],
        ];
    }
}
