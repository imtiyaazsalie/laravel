<?php

namespace App\Http\Requests\Reports\Report;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CoachClassOrSessionDetailsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
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
            'filter.user_id' => ['required', 'integer', 'exists:users,user_id'],
            'filter.tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'filter.location_id' => ['sometimes', 'integer', 'exists:box_facility,box_facility_id'],
            'filter.start_date' => 'required|date',
            'filter.end_date' => 'required|date|after_or_equal:start_date',
            'filter.is_session' => ['required', new BooleanRule()],
            'per_page' => new PerPageRule(),
        ];
    }
}
