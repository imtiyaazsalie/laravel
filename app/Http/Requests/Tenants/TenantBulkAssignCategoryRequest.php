<?php

namespace App\Http\Requests\Tenants;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class TenantBulkAssignCategoryRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_ids.*' => 'required|exists:boxes,box_id',
            'location_category_id' => 'required|integer|exists:box_facility_categories,box_facility_category_id',
        ];
    }
}
