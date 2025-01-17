<?php

namespace App\Http\Requests\Reports\Attendance;

use App\Enums\UserType;
use App\Models\Location;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class MailNonAttendanceMembersRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $location = Location::findOrFail($this->location_id);

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
            'location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'message' => 'required|string|min:10',
            'subject' => 'required|string|min:10|max:120',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];
    }
}
