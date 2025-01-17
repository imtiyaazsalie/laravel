<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ValidateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'email' => 'required|email',
            'password' => auth()->check() ? 'nullable' : ['required', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => 'User could not be found.',
        ];
    }
}
