<?php

namespace App\Http\Requests\Tenants;

use App\Enums\Affiliate;
use App\Enums\TenantStatus;
use App\Enums\UserType;
use App\Rules\EnumRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class TenantListRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.region_id' => 'nullable|integer|exists:regions,region_id',
            'filter.affiliate_id' => ['nullable', new EnumRule(Affiliate::class)],
            'filter.status_id' => ['nullable', new EnumRule(TenantStatus::class)],
            'filter.search' => 'nullable|string|min:2|max:60',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
