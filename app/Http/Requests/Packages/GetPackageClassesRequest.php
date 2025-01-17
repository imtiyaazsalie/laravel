<?php

namespace App\Http\Requests\Packages;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class GetPackageClassesRequest extends FormRequest
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
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.location_id' => 'required|integer|exists:box_facility,box_facility_id',
        ];
    }
}
