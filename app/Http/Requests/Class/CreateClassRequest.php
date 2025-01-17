<?php

namespace App\Http\Requests\Class;

use App\Enums\ClassType;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\TagRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateClassRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: $this->tenant_id,
            locationId: $this->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'location_id' => ['required', 'integer', 'exists:box_facility,box_facility_id'],
            'is_session' => ['required', new BooleanRule()],
            'name' => 'required|string|min:1|max:254',
            'description' => 'nullable|string|min:1',
            'instructor_id' => 'required|integer|exists:users,user_id',
            'supporting_instructor_id' => 'nullable|integer|exists:users,user_id',
            'is_display_coach_name' => ['required', new BooleanRule()],
            'is_free' => ['required', new BooleanRule()],
            'class_type_id' => ['required', new Enum(ClassType::class)],
            'once_off_date' => [
                'nullable',
                'required_if:class_type_id,'.ClassType::ONCE_OFF->value,
                'date',
            ],
            'recurring_days' => [
                'nullable',
                'required_if:class_type_id,'.ClassType::RECURRING->value,
                'array',
            ],
            'recurring_days.*' => [
                'integer',
                'between:1,7',
            ],
            'recurring_start_date' => [
                'nullable',
                'required_if:class_type_id,'.ClassType::RECURRING->value,
                'date',
            ],
            'recurring_end_date' => [
                'nullable',
                'date',
                'after:recurring_start_date',
            ],
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
            'is_visible_in_app' => ['required', new BooleanRule()],
            'limit' => 'required|integer',
            'booking_threshold' => 'required|integer',
            'cancellation_threshold' => 'required|integer',
            'package_ids' => 'nullable|array',
            'package_ids.*' => 'integer|exists:packages,package_id',
            'meeting_url' => 'nullable|active_url',
            'is_virtual' => ['required', new BooleanRule()],
            'tag_ids' => new TagRule('location', 'box_facility', $this->location_id),
            'min_booked_members_count' => 'nullable|integer|required_with:auto_cancel_threshold_min',
            'auto_cancel_threshold_min' => 'nullable|integer|required_with:min_booked_members_count',
        ];
    }
}
