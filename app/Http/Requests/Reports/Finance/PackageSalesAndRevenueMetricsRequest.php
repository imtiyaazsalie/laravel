<?php

namespace App\Http\Requests\Reports\Finance;

use App\Enums\InvoicePaymentType;
use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\TagRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Enum;

class PackageSalesAndRevenueMetricsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            permission: 'reports',
            tenantId: Tenant::query()->findOrFail($this->input('filter.tenant_id'))?->getKey(),
            locationId: $this->input('filter.location_id'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'filter.location_id' => ['nullable', 'integer', 'exists:box_facility,box_facility_id'],
            'filter.start_date' => 'required|date',
            'filter.end_date' => 'required|date|after_or_equal:start_date',
            'filter.payment_type' => ['nullable', new Enum(InvoicePaymentType::class)],
            'filter.tag_id' => ['nullable', 'integer', new TagRule('package', 'boxes', Arr::get($this->filter, 'tenant_id'))],
        ];
    }
}
