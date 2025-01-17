<?php

namespace App\Http\Requests\Affiliates;

use App\Enums\Affiliate;
use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UnlinkTenantAffiliateRequest extends FormRequest
{
    use Authorize;

    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR,
        );
    }

    public function rules(): array
    {
        return [
            'affiliate_id' => ['required', new Enum(Affiliate::class)],
            'tenant_ids' => 'required|array|exists:boxes,box_id',
        ];
    }
}
