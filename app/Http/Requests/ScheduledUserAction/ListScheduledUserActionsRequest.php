<?php

namespace App\Http\Requests\ScheduledUserAction;

use App\Enums\ScheduleUserAction;
use App\Enums\ScheduleUserActionStatus;
use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\EnumRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListScheduledUserActionsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $tenant = Tenant::findOrFail($this->input('filter.tenant_id'));

        return $this->canOperate(
            tenantId: $tenant->getKey(),
            locationId: Arr::get($this->filter, 'location_id'),
            userId: Arr::get($this->filter, 'user_id'),
            userTypes: UserType::locationAdmins()
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
            'filter.user_id' => 'nullable|integer|exists:users,user_id',
            'filter.status' => ['nullable', new EnumRule(ScheduleUserActionStatus::class)],
            'filter.action' => ['nullable', new EnumRule(ScheduleUserAction::class)],
            'filter.search' => 'nullable|string',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
