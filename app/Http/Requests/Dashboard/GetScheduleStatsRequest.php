<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class GetScheduleStatsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->input('filter.tenant_id'),
            locationId: $this->input('filter.location_id'),
            userTypes: UserType::AllRoles()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'filter.location_id' => ['required', 'integer', 'exists:box_facility,box_facility_id'],
        ];
    }
}
