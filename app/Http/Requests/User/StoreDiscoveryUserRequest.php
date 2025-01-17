<?php

namespace App\Http\Requests\User;

use App\Rules\IdNumberRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiscoveryUserRequest extends FormRequest
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
            'name' => 'required|string|min:1|max:120',
            'surname' => 'required|string|min:1|max:120',
            'id_number' => ['required', new IdNumberRule()],
            'date_of_birth' => 'required|date|before:today',
            'email' => 'required|email|unique:users,email_alt',
            'health_provider_id' => 'required|integer|exists:health_providers,id',
        ];
    }
}
