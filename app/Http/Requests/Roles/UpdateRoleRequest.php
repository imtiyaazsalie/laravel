<?php

namespace App\Http\Requests\Roles;

use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:60|not_in:'.config('octiv.gym_super_admin_role'),
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'required|string',
        ];
    }
}
