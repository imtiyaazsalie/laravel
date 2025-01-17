<?php

namespace App\Http\Requests\ClassBooking;

use App\Enums\UserType;
use App\Models\ClassDate;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateClassBookingRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $classDate = ClassDate::with('class')->findOrFail($this->class_date_id);

        //TODO: Handle overbook permission.
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $classDate->class->tenant_id,
            locationId: $classDate->class->location_id,
            allowMember: true,
            userId: $this->user_id,
            scope: 'discovery-vitality'
            // permission: 'class_booking_overbook_member'
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'class_date_id' => ['required', 'integer', 'exists:class_to_dates,class_to_date_id'],
            'user_id' => 'required_without:non_member|integer|exists:users,user_id',
            'user_package_id' => 'sometimes:integer|exists:user_to_package,user_to_package_id',
            'non_member' => 'required_without:user_id|array:name,email',
            'non_member.name' => 'string',
            'non_member.email' => 'required_with:non_member|email:rfc,dns',
        ];
    }
}
