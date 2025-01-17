<?php

namespace App\Http\Requests\Reports\Attendance;

use App\Enums\ClassBookingStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ClassAttendanceDetailsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $class = Classes::findOrFail($this->input('filter.class_id'));

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $class->tenant_id,
            locationId: $class->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.class_id' => ['required', 'integer', 'exists:classes,class_id'],
            'filter.booking_status_id' => ['required', new Enum(ClassBookingStatus::class)],
            'filter.start_date' => 'required|date',
            'filter.end_date' => 'required|date|after_or_equal:start_date',
        ];
    }
}
