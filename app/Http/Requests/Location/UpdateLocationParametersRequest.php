<?php

namespace App\Http\Requests\Location;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationParametersRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('location')->tenant_id,
            userTypes: UserType::locationAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'bank_user_code' => 'required',
            'bank_user_name' => 'required',
            'bank_nominated_account' => 'required',
        ];
    }
}
