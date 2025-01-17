<?php

namespace App\Http\Requests;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Response;

class SendWelcomeEmailRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->input('tenant_id'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'users' => 'required|array',
            'users.*' => 'int|exists:users,user_id',
            'tenant_id' => 'required|int|exists:boxes,box_id',
        ];
    }
}
