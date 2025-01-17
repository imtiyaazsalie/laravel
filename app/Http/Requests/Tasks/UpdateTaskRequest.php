<?php

namespace App\Http\Requests\Tasks;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('task')->tenant_id,
            locationId: $this->has('location_id') ? $this->location_id : $this->route('task')->location_id,
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', 'exists:box_facility,box_facility_id'],
            'assigned_user_ids' => ['nullable', 'array', 'exists:users,user_id'],
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'is_completed' => ['required', new BooleanRule()],
        ];
    }
}
