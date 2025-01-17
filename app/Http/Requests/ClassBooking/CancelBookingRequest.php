<?php

namespace App\Http\Requests\ClassBooking;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->route('booking')->class->tenant_id,
            allowMember: ! is_null($this->route('booking')->user_id),
            userId: $this->route('booking')->user_id,
            scope: 'discovery-vitality'
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'is_late_cancellation' => [new BooleanRule()],
        ];
    }
}
