<?php

namespace App\Http\Requests\Location;

use App\Enums\PaymentGateway;
use App\Enums\TenantStatus;
use App\Rules\BooleanRule;
use App\Rules\EnumRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListLocationRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.region_id' => ['nullable', 'integer', 'exists:regions,region_id'],
            'filter.location_id' => ['nullable', 'integer', 'exists:box_facility,box_facility_id'],
            'filter.billing_payment_gateway_id' => ['nullable', new EnumRule(PaymentGateway::class)],
            'filter.payment_gateway_id' => ['nullable', new EnumRule(PaymentGateway::class)],
            'filter.tenant_status_id' => ['nullable', new EnumRule(TenantStatus::class)],
            'filter.health_provider_id' => ['nullable', 'integer', 'exists:health_providers,id'],
            'filter.amenity_id' => ['nullable', 'integer', 'exists:amenities,id'],
            'filter.is_active' => [new BooleanRule()],
            'filter.search' => ['nullable', 'string', 'min:3', 'max:120'],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
