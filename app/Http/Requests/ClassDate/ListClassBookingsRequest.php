<?php

namespace App\Http\Requests\ClassDate;

use App\Enums\ClassBookingStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Rules\DatesBetweenRule;
use App\Rules\EnumRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListClassBookingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $tenantId = Arr::get(
            $this->filter,
            'tenant_id',
            Location::find(Arr::get($this->filter, 'location_id'))?->tenant_id
        );

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $tenantId,
            locationId: Arr::get($this->filter, 'location_id'),
            allowMember: ! is_null(Arr::get($this->filter, 'user_id')),
            userId: $this->user()->getKey(),
            scope: 'discovery-vitality'
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.user_type_id' => ['nullable', new EnumRule(UserType::class)],
            'filter.location_id' => 'nullable|integer|exists:box_facility,box_facility_id',
            'filter.tenant_id' => ['nullable', 'integer', 'exists:boxes,box_id'],
            'filter.lead_member_id' => ['nullable', 'integer', 'exists:lead_members,member_id'],
            'filter.user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'filter.status' => ['nullable', new EnumRule(ClassBookingStatus::class)],
            'filter.class_date_id' => ['nullable', 'exists:class_to_dates,class_to_date_id'],
            'filter.between' => [new DatesBetweenRule()],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
