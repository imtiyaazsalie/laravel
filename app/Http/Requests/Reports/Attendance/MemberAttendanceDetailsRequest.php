<?php

namespace App\Http\Requests\Reports\Attendance;

use App\Enums\ClassBookingStatus;
use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MemberAttendanceDetailsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->input('filter.tenant_id'),
            locationId: $this->input('filter.location_id'),
            userId: $this->input('filter.user_id'),
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
            'filter.user_id' => ['required', 'integer', 'exists:users,user_id'],
            'filter.class_id' => ['nullable', 'integer', 'exists:classes,class_id'],
            'filter.booking_status_id' => ['required', new Enum(ClassBookingStatus::class)],
            'filter.start_date' => 'required|date',
            'filter.end_date' => 'required|date|after_or_equal:start_date',
        ];
    }
}
