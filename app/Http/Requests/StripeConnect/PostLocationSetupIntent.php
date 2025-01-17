<?php

namespace App\Http\Requests\StripeConnect;

use App\Enums\UserType;
use App\Models\Location;
use App\Models\User;
use App\Traits\Authorize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PostLocationSetupIntent extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $location = Location::findOrFail($this->input('location_id'));

        return $this->canOperate(
            userTypes: UserType::HEAD_COACH,
            tenantId: $location->tenant_id,
            locationId: $location->getKey()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:box_facility,box_facility_id'],
        ];
    }
}
