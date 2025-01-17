<?php

namespace App\Http\Requests\UserPackage;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\DatesBetweenRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ExportUserPackagesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: Arr::get($this->filter, 'tenant_id'),
            locationId: Arr::get($this->filter, 'location_id'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.user_id' => 'nullable|integer|exists:users,user_id',
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.location_id' => 'nullable|integer|exists:box_facility,box_facility_id',
            'filter.package_id' => ['nullable', 'integer', 'exists:packages,package_id'],
            'filter.starts_between' => ['nullable', new DatesBetweenRule],
            'filter.ends_between' => ['nullable', new DatesBetweenRule],
            'filter.is_active' => ['nullable', new BooleanRule],
            'filter.is_package_active' => ['nullable', new BooleanRule],
            'filter.is_session_available' => ['nullable', new BooleanRule],
        ];
    }
}
