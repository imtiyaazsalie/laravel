<?php

namespace App\Http\Requests\Admin\Location;

use App\Enums\TenantStatus;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AdminListLocationRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => ['nullable', 'exists:boxes,box_id'],
            'filter.region_id' => ['nullable', 'exists:regions,region_id'],
            'filter.payment_gateway_id' => ['nullable', 'exists:payment_gateways,payment_gateway_id'],
            'filter.tenant_status_id' => ['nullable', new Enum(TenantStatus::class)],
            'filter.health_provider_id' => ['nullable', 'exists:health_providers,id'],
            'filter.search' => ['nullable', 'string', 'min:3', 'max:120'],
            'filter.is_active' => ['required', new BooleanRule()],
            'per_page' => new PerPageRule(),
        ];
    }
}
