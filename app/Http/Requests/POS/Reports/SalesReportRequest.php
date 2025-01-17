<?php

namespace App\Http\Requests\POS\Reports;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class SalesReportRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $tenant = Tenant::findOrFail($this->input('filter.tenant_id'));

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $tenant->getKey(),
            locationId: $this->input('filter.location_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.location_id' => 'nullable|integer|exists:box_facility,box_facility_id',
            'filter.status' => 'nullable|in:paid,cancelled,invoiced,parked,open',
            'filter.start_date' => 'nullable|date',
            'filter.end_date' => 'required_with:filter.start_date|date|after_or_equal:filter.start_date',
            'sort' => 'nullable|string|in:purchaser.name,pos_sales.status,pos_sales.created_on',
            'order' => 'nullable|string|in:ASC,DESC',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'sort.in' => 'The selected sort must be \'purchaser.name\', \'pos_sales.status\' or \'pos_sales.created_on\'.',
            'order.in' => 'The selected order must be \'ASC\' or \'DESC\'.',
        ];
    }
}
