<?php

namespace App\Http\Requests\Reports\Report;

use App\Enums\UserType;
use App\Models\Location;
use App\Models\Tenant;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ExportRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            permission: 'reports',
            tenantId: Tenant::query()->findOrFail($this->input('filter.tenant_id'))?->getKey(),
            locationId: Location::query()->find($this->input('filter.location_id'))?->getKey(),
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.report_type' => 'required|in:members,finances,bookings,leads',
            'filter.location_id' => ['nullable', 'integer', 'exists:box_facility,box_facility_id'],
            'filter.start_date' => 'nullable|date',
            'filter.end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
