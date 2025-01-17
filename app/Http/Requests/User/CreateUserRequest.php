<?php

namespace App\Http\Requests\User;

use App\Enums\Gender;
use App\Rules\IdNumberRule;
use App\Rules\ImageRule;
use App\Rules\MobileNumberRule;
use App\Traits\Authorize;
use App\Traits\ValidatesContractDetails;
use App\Traits\ValidatesDiscountDetails;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class CreateUserRequest extends FormRequest
{
    use Authorize, ValidatesContractDetails, ValidatesDiscountDetails;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:120',
            'surname' => 'required|string|min:1|max:120',
            'gender_id' => ['nullable', new Enum(Gender::class)],
            'id_number' => ['nullable', new IdNumberRule()],
            'address' => 'nullable|string',
            'health_provider_id' => 'nullable|integer|exists:health_providers,id',
            'date_of_birth' => 'nullable|date|before:today',
            'mobile' => ['nullable', new MobileNumberRule()],
            'emergency_contact_name' => 'nullable|string|min:1|max:120',
            'emergency_contact_mobile' => ['nullable', new MobileNumberRule()],
            'image' => ['nullable', new ImageRule()],
            'password' => [
                auth()->check() ? 'nullable' : 'required',
                Password::defaults(),
            ],
            'email' => ['required', 'email:rfc,dns', Rule::unique('users')->where(function ($query) {
                return $query->whereNull('deleted');
            }),
            ],
        ];
    }
}
