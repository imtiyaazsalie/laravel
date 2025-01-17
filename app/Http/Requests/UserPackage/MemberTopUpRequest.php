<?php

namespace App\Http\Requests\UserPackage;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class MemberTopUpRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $userPackage = $this->route('userPackage');

        return $this->canOperate(
            tenantId: $userPackage->package->tenant_id,
            userTypes: UserType::GYM_MEMBER,
            allowMember: true
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'sessions' => 'required|integer',
        ];
    }
}
