<?php

namespace App\Http\Requests\OperatingHours;

use App\Enums\Day;
use App\Enums\UserType;
use App\Rules\UniqueOpeningTimeRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateOperatingHoursRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('operating_hour')->location->tenant_id,
            locationId: $this->route('operating_hour')->location_id,
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'day' => ['required', new Enum(Day::class)],
            'opening_time' => ['required', 'date_format:H:i', new UniqueOpeningTimeRule($this->route('operating_hour'))],
            'closing_time' => 'required|date_format:H:i',
        ];
    }
}
