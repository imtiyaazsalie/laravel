<?php

namespace App\Http\Requests\ClassRecurringBookings;

use App\Enums\UserType;
use App\Models\Classes;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateRecurringBookingRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $class = Classes::findOrFail($this->class_id);

        return $this->canOperate(
            tenantId: $class->tenant_id,
            locationId: $class->location_id,
            userId: $this->user_id,
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,class_id'],
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'days_of_the_week' => 'required|array',
            'days_of_the_week.*' => 'numeric|between:1,7',
            'ending_on_date' => 'nullable|date',
        ];
    }
}
