<?php

namespace App\Http\Requests\Packages;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageClassesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantAdmins(),
            tenantId: $this->route('package')->tenant_id,
            locationId: $this->location_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:box_facility,box_facility_id'],
            'class_ids' => ['nullable', 'array', 'exists:classes,class_id'],
        ];
    }
}
