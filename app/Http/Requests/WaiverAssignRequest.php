<?php

namespace App\Http\Requests;

use App\Enums\UserType;
use App\Models\Location;
use App\Models\User;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WaiverAssignRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $location = Location::findOrFail($this->input('location_id'));

        return $this->canOperate(
            userTypes: UserType::HEAD_COACH,
            tenantId: $location->tenant_id,
            locationId: $location->getKey(),
            userId: User::query()->findOrFail($this->input('user_id'))->getKey()
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
            'waiver_id' => 'required|integer|exists:lead_waivers,waiver_id',
            'user_id' => 'required|integer|exists:users,user_id',
            'location_id' => 'required|integer|exists:box_facility,box_facility_id',
        ];
    }
}
