<?php

namespace App\Http\Requests\CoachRate;

use App\Enums\CoachRateStrategy;
use App\Enums\CoachRateType;
use App\Enums\UserType;
use App\Models\Location;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateCoachRateRequest extends FormRequest
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
            locationId: $this->location_id,
            userId: $this->user_id,
            userTypes: [
                UserType::HEAD_COACH,
                UserType::BOX_ADMIN,
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'location_id' => ['required', 'integer', 'exists:box_facility,box_facility_id'],

            'strategy' => ['required', new Enum(CoachRateStrategy::class)],
            'type' => ['required', new Enum(CoachRateType::class)],
            'amount' => ['required', new PriceRule()],

            'min_members' => 'required_if:strategy,'.CoachRateStrategy::MEMBER_BASED->value,
            'max_members' => 'required_if:strategy,'.CoachRateStrategy::MEMBER_BASED->value,
        ];
    }
}
