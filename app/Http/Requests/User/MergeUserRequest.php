<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class MergeUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'primary_user_id' => ['required', 'integer', 'exists:users,user_id'],
            'merge_user_ids' => ['sometimes', 'array'],
            'merge_user_ids.*' => ['sometimes', 'integer', 'exists:users,user_id', 'different:primary_user_id'],
            'update_users' => ['sometimes', 'array'],
            'update_users.*.user_id' => ['required_with:update_users.*.email', 'integer', 'exists:users,user_id', 'different:primary_user_id', 'different:merge_user_ids.*'],
            'update_users.*.email' => ['required_with:update_users.*.user_id', 'email'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
