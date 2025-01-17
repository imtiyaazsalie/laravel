<?php

namespace App\Http\Requests\Class;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\TagRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClassRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            permission: 'class_actions',
            tenantId: $this->route('class')->tenant_id,
            locationId: $this->route('class')->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'string',
            'description' => 'nullable|string',
            'meeting_url' => 'nullable|active_url',
            'limit' => 'integer',
            'location_id' => 'integer|exists:box_facility,box_facility_id',
            'instructor_id' => 'required|integer|exists:users,user_id',
            'supporting_instructor_id' => 'nullable|integer|exists:users,user_id',
            'package_ids' => 'nullable|array',
            'package_ids.*' => 'integer|exists:packages,package_id',
            'is_display_coach_name' => [new BooleanRule()],
            'is_session' => [new BooleanRule()],
            'is_free' => [new BooleanRule()],
            'is_visible_in_app' => [new BooleanRule()],
            'is_virtual' => [new BooleanRule()],
            'from_date' => 'nullable|date',
            'start_time' => 'date_format:H:i:s',
            'end_time' => 'date_format:H:i:s',
            'booking_threshold' => 'integer',
            'cancellation_threshold' => 'integer',
            'tag_ids' => ['sometimes', 'nullable', new TagRule('location', 'box_facility', $this->route('class')->location_id)],
            'min_booked_members_count' => 'nullable|integer|required_with:auto_cancel_threshold_min',
            'auto_cancel_threshold_min' => 'nullable|integer|required_with:min_booked_members_count',
        ];

        if ($this->route('class')?->isOnceOff()) {
            $rules['once_off_date'] = 'required|date';
        } else {
            $rules['recurring_days'] = 'required|array';
            $rules['recurring_days.*'] = 'integer|between:1,7';
            $rules['recurring_end_date'] = 'nullable|date|after:today|after:from_date';
        }

        return $rules;
    }
}
