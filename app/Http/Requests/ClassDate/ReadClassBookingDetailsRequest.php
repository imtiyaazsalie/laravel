<?php

namespace App\Http\Requests\ClassDate;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ReadClassBookingDetailsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::allRoles(),
            tenantId: $this->route('classDate')->class->tenant_id,
            locationId: $this->route('classDate')->class->location_id,
            allowMember: true
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
