<?php

namespace App\Http\Requests\Mandate;

use App\Enums\UserType;
use App\Models\Mandate;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ShowMandateRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        /** @var Mandate $mandate */
        $mandate = $this->route('mandate');

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $mandate->tenant_id,
            allowMember: true,
            userId: $mandate->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
