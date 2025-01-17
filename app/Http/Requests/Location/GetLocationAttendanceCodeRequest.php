<?php

namespace App\Http\Requests\Location;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class GetLocationAttendanceCodeRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('location')->tenant_id,
            locationId: $this->route('location')->getKey(),
            allowLocationCheckIn: true,
            userTypes: [
                ...UserType::tenantStaff(),
                UserType::LOCATION_CHECK_IN,
            ],
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
