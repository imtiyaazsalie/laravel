<?php

namespace App\Http\Requests\ClassDate;

use App\Enums\UserType;
use App\Rules\TagRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClassDateRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: $this->route('classDate')->class->tenant_id,
            locationId: $this->route('classDate')->class->location_id,
            permissions: [
                'default' => ['class_actions', 'class_single_actions'],
                UserType::GYM_COACH->value => null,
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s|after_or_equal:start_date',
            'limit' => 'nullable|integer',
            'meeting_url' => 'nullable|active_url',
            'instructor_id' => 'nullable|integer|exists:users,user_id',
            'supporting_instructor_id' => 'nullable|integer|exists:users,user_id',
            'name' => 'nullable|string|min:3|max:255',
            'description' => 'nullable|string',
            'tag_ids' => ['nullable', 'array', new TagRule('location', 'box_facility', $this->route('classDate')->class->location_id)],
            'min_booked_members_count' => 'nullable|integer|required_with:auto_cancel_threshold_min',
            'auto_cancel_threshold_min' => 'nullable|integer|required_with:min_booked_members_count',
        ];
    }
}
