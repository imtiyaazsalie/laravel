<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateUserTenantsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // 'primary_user_id' => ['required', 'exists:users,user_id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
