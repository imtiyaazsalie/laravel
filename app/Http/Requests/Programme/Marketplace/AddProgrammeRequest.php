<?php

namespace App\Http\Requests\Programme\Marketplace;

use App\Enums\UserType;
use App\Rules\GlobalProgrammeRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class AddProgrammeRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            tenantId: $this->tenant_id,
            userTypes: UserType::tenantAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'programme_id' => [
                'required',
                'integer',
                new GlobalProgrammeRule(
                    tenantId: $this->tenant_id
                ),
            ],
        ];
    }
}
