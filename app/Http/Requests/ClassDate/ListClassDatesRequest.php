<?php

namespace App\Http\Requests\ClassDate;

use App\Rules\BooleanRule;
use App\Rules\DatesBetweenRule;
use App\Rules\PerPageRule;
use App\Rules\TagRule;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Spatie\ValidationRules\Rules\Delimited;

class ListClassDatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.id' => 'sometimes|integer|exists:class_to_dates,class_to_date_id',
            'filter.tenant_id' => 'integer|exists:boxes,box_id',
            'filter.location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'filter.package_type_id' => 'nullable|integer',
            'filter.package_ids' => ['nullable', new Delimited('integer')],
            'filter.between' => ['required', new DatesBetweenRule()],
            'filter.class_id' => 'nullable|integer|exists:classes,class_id',
            'filter.instructor_id' => 'nullable|integer|exists:users,user_id',
            'filter.supporting_instructor_id' => 'nullable|integer|exists:users,user_id',
            'filter.is_session' => ['nullable', new BooleanRule],
            'filter.is_visible_in_app' => ['nullable', new BooleanRule],
            'filter.tag_ids' => ['nullable', 'array', new TagRule('location', 'box_facility', Arr::get($this->filter, 'location_id'))],
            'filter.is_active' => ['nullable', new BooleanRule],
            'filter.class_bookings_for_lead_token' => 'nullable',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
