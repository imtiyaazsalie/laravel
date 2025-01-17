<?php

namespace App\Http\Requests\Class;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ReadClassRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: $this->route('class')->tenant_id,
            locationId: $this->route('class')->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
