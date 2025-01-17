<?php

namespace App\Http\Requests\Reports\Attendance;

use App\Enums\UserType;
use App\Models\Location;
use App\Rules\DatesBetweenRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class MemberNonAttendanceRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $location = Location::findOrFail($this->input('filter.location_id'));

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $location->tenant_id,
            locationId: $location->getKey()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'filter.between' => ['required', new DatesBetweenRule],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
