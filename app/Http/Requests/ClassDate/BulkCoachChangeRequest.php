<?php

namespace App\Http\Requests\ClassDate;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class BulkCoachChangeRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: Tenant::query()->findOrFail($this->input('tenant_id'))?->tenant_id,
            permissions: [
                'default' => ['class_actions', 'class_bulk_actions'],
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
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'class_date_ids' => ['required', 'array', 'exists:class_to_dates,class_to_date_id'],
            'instructor_id' => ['required', 'integer', 'exists:users,user_id'],
        ];
    }
}
