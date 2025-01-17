<?php

namespace App\Http\Requests\ClassBookingWaiting;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class LeaveWaitingListRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('booking')->class->tenant_id,
            locationId: $this->route('booking')->class->location_id,
            allowMember: true,
            userId: $this->route('booking')->user_id,
            userTypes: UserType::tenantUsers(),
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
