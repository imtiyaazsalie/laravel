<?php

namespace App\Http\Requests\POS\Reports;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class StockItemsReportRequest extends FormRequest
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
            'filter.location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'filter.start_date' => 'nullable|date',
            'filter.end_date' => 'nullable|date|after_or_equal:start_date',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
