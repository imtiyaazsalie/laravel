<?php

namespace App\Http\Requests\Wod;

use App\Enums\Affiliate;
use App\Enums\UserType;
use App\Rules\CSVRule;
use App\Rules\EnumRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ImportWodsRequest extends FormRequest
{
    use Authorize;

    public function authorize(): bool|Response
    {
        return $this->canOperate(
            tenantId: $this->tenant_id,
            userTypes: [
                ...UserType::tenantStaff(),
                UserType::SUPER_ADMINISTRATOR,
            ],
            permission: 'wod_actions',
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
            'tenant_id' => 'required_without:affiliate_id|integer|exists:boxes,box_id',
            'affiliate_id' => ['nullable', new EnumRule(Affiliate::class)],
            'file' => ['required', new CSVRule()],
        ];
    }
}
