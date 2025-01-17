<?php

namespace App\Http\Requests\Whiteboard;

use App\Enums\UserType;
use App\Models\Location;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class WhiteboardRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: Location::query()->findOrFail($this->input('filter.location_id'))->tenant_id,
            locationId: Location::query()->findOrFail($this->input('filter.location_id'))->getKey()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'sort_by' => 'nullable|alpha|min:1|max:1',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'filter.location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'filter.date' => 'required|date',
            'filter.programme_id' => 'required|integer|exists:programmes,id',
            'filter.class_date_id' => ['nullable', 'integer', 'exists:class_to_dates,class_to_date_id'],
            'filter.show_none_booking_members' => new BooleanRule,
        ];
    }
}
