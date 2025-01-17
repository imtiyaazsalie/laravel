<?php

namespace App\Http\Requests\Tasks;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->tenant_id,
            locationId: $this->location_id,
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Set defaults
     */
    public function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'is_completed' => false,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'assigned_user_ids' => ['nullable', 'array', 'exists:users,user_id'],
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'is_completed' => ['required', new BooleanRule()],
        ];
    }
}
