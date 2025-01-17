<?php

namespace App\Http\Requests\OperatingHours;

use App\Enums\UserType;
use App\Models\Location;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListOperatingHoursRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $location = Location::findOrFail($this->input('filter.location_id'));

        return $this->canOperate(
            tenantId: $location->tenant_id,
            locationId: $this->input('filter.location_id'),
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.location_id' => 'required|integer|exists:box_facility,box_facility_id',
        ];
    }
}
