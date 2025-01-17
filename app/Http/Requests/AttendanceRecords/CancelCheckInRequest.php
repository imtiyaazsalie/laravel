<?php

namespace App\Http\Requests\AttendanceRecords;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CancelCheckInRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('classBooking')->class->tenant_id,
            locationId: $this->route('classBooking')->class->location_id,
            userId: $this->route('classBooking')->user_id,
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
