<?php

namespace App\Http\Requests\Reports\Attendance;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\DatesBetweenRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class MemberAttendanceRequest extends FormRequest
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
            'filter.is_session' => ['nullable', new BooleanRule],
            'filter.between' => ['required', new DatesBetweenRule()],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
