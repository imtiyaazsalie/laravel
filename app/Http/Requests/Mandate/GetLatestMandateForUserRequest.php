<?php

namespace App\Http\Requests\Mandate;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class GetLatestMandateForUserRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: [UserType::GYM_MEMBER],
            tenantId: $this->input('tenant_id'),
            allowMember: true,
            userId: $this->route('userId')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
        ];
    }
}
