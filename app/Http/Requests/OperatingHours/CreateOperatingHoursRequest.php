<?php

namespace App\Http\Requests\OperatingHours;

use App\Enums\Day;
use App\Enums\UserType;
use App\Models\Location;
use App\Rules\UniqueOpeningTimeRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateOperatingHoursRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $location = Location::findOrFail($this->location_id);

        return $this->canOperate(
            tenantId: $location->tenant_id,
            locationId: $location->getKey(),
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'day' => ['required', new Enum(Day::class)],
            'opening_time' => ['required', 'date_format:H:i', new UniqueOpeningTimeRule],
            'closing_time' => 'required|date_format:H:i',
        ];
    }
}
