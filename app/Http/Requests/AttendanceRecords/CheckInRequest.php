<?php

namespace App\Http\Requests\AttendanceRecords;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Rules\IdNumberRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        if (! auth()->check()) {
            return true;
        }

        $classBooking = $this->has('class_booking_id') ? ClassBooking::query()->findOrFail($this->class_booking_id) : null;
        $classDate = $this->has('class_date_id') ? ClassDate::query()->findOrFail($this->class_date_id) : null;

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $classBooking?->class->tenant_id ?: $classDate?->class->tenant_id ?: $this->tenant_id,
            locationId: $classBooking?->class->location_id ?: $classDate?->class->location_id,
            allowMember: ! is_null($classBooking?->user_id),
            userId: $classBooking?->user_id,
            userIdStatuses: UserStatus::allStatuses(),
            scope: 'discovery-vitality'
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'sometimes|integer|exists:boxes,box_id',
            'class_booking_id' => 'sometimes|integer|exists:class_bookings,class_booking_id',
            'class_date_id' => ['sometimes', 'integer', 'exists:class_to_dates,class_to_date_id'],
            'code' => ['sometimes', 'uuid'],

            'id_number' => ! auth()->check() ? ['required', new IdNumberRule()] : '',
            'name' => ! auth()->check() ? 'string|regex:/^([^0-9]*)$/' : '',
            'surname' => ! auth()->check() ? 'string|regex:/^([^0-9]*)$/' : '',
            'date_of_birth' => ! auth()->check() ? 'date|after:1900-01-01|before:today' : '',
            'health_provider_id' => ! auth()->check() ? 'integer|exists:health_providers,id' : '',
        ];
    }

    public function messages(): array
    {
        return [
            'class_booking_id.unique' => 'Attendance record already exists for class booking.',
        ];
    }
}
