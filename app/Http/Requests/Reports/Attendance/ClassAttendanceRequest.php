<?php

namespace App\Http\Requests\Reports\Attendance;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\DatesBetweenRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ClassAttendanceRequest extends FormRequest
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
            locationId: $this->input('filter.location_id')
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
            'filter.class_id' => ['nullable', 'integer', 'exists:classes,class_id'],
            'filter.class_date_id' => ['sometimes', 'integer', 'exists:class_to_dates,class_to_date_id'],
            'filter.is_session' => ['nullable', new BooleanRule],
            'filter.is_class_active' => ['nullable', new BooleanRule],
            'filter.user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'filter.class_dates_between' => ['required', new DatesBetweenRule],
        ];
    }
}
