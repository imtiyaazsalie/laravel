<?php

namespace App\Http\Requests\Admin\PrivacyPolicy;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class CreatePrivacyPolicyRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function authorize()
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'content' => 'required|string',
            'should_users_accept' => ['required', new BooleanRule()],
        ];
    }
}
