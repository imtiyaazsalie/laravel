<?php

namespace App\Http\Requests\LinkedUser;

use App\Models\User;
use App\Traits\Authorize;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class storeLinkedUserRequest extends FormRequest
{
    use Authorize;

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
            'email' => [
                'required',
                'exists:users,email',
            ],
            'password' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (! Hash::check($value, User::query()->where('email', $this->email)->firstOrFail()->password)) {
                    $fail("The $attribute is invalid.");
                }
            }],
        ];
    }
}
