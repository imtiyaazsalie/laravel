<?php

namespace App\Http\Requests\ClassBookingWaiting;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class MessageWaitingListBookingAthleteRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            tenantId: $this->route('booking')->class->tenant_id,
            locationId: $this->route('booking')->class->location_id,
            userId: $this->route('booking')->user_id,
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string|min:10',
        ];
    }
}
