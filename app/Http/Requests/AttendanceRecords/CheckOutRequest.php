<?php

namespace App\Http\Requests\AttendanceRecords;

use App\Enums\UserType;
use App\Models\ClassBooking;
use App\Rules\IdNumberRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CheckOutRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        if (! auth()->check()) {
            return true;
        }

        $classBooking = ClassBooking::query()
            ->findOrFail($this->class_booking_id);

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $classBooking->class->tenant_id,
            locationId: $classBooking->class->location_id,
            allowMember: ! is_null($classBooking->user_id),
            userId: $classBooking->user_id,
            scope: 'discovery-vitality'
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'uuid'],
            'class_booking_id' => auth()->check() ? ['required', 'exists:class_bookings,class_booking_id'] : '',
            'id_number' => auth('api')->check() ? ['nullable'] : ['required', new IdNumberRule()],
        ];
    }
}
