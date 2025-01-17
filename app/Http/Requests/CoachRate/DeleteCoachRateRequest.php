<?php

namespace App\Http\Requests\CoachRate;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCoachRateRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('rate')->location->tenant_id,
            locationId: $this->route('rate')->location_id,
            userId: $this->route('rate')->user_id,
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
        return [];
    }
}
