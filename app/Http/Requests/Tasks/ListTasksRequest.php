<?php

namespace App\Http\Requests\Tasks;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListTasksRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: Arr::get($this->filter, 'tenant_id'),
            locationId: Arr::get($this->filter, 'location_id'),
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'filter.location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'filter.assignee_user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'filter.is_completed' => new BooleanRule(),
            'filter.is_scheduled' => new BooleanRule(),
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
