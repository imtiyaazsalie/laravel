<?php

namespace App\Http\Requests\ClassBookingWaiting;

use App\Enums\UserType;
use App\Models\ClassDate;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateWaitingListBookingRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $classDate = ClassDate::with('class')->findOrFail($this->class_date_id);

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->tenant_id,
            locationId: $classDate->class->location_id,
            allowMember: true,
            userId: $this->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,user_id',
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'class_date_id' => ['required', 'integer', 'exists:class_to_dates,class_to_date_id'],
        ];
    }
}
