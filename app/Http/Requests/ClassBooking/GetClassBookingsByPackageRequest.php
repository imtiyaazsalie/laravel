<?php

namespace App\Http\Requests\ClassBooking;

use App\Enums\UserType;
use App\Rules\DatesBetweenRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class GetClassBookingsByPackageRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->route('package')->tenant_id,
            allowMember: true,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.location_id' => ['nullable', 'integer', 'exists:box_facility,box_facility_id'],
            'filter.class_date_between' => ['required', new DatesBetweenRule()],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
