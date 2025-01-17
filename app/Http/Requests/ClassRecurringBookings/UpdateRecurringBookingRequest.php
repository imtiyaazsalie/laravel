<?php

namespace App\Http\Requests\ClassRecurringBookings;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringBookingRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('booking')->class->tenant_id,
            locationId: $this->route('booking')->class->location_id,
            userId: $this->route('booking')->user_id,
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'days_of_the_week' => 'required|array',
            'days_of_the_week.*' => 'numeric|between:1,7',
            'ending_on_date' => 'nullable|date',
        ];
    }
}
