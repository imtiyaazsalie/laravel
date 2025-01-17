<?php

namespace App\Http\Requests\POS\Reports;

use App\Enums\UserType;
use App\Models\Location;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class SalesDetailsReportRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: Location::query()->findOrFail($this->input('filter.location_id'))->box_id,
            locationId: $this->input('filter.location_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'filter.start_date' => 'required|date',
            'filter.end_date' => 'required|date|after_or_equal:start_date',
        ];
    }
}
