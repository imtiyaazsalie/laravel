<?php

namespace App\Http\Requests\Class;

use App\Enums\ClassType;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Rules\TagRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Enum;

class ListClassesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: Arr::get($this->filter, 'tenant_id'),
            locationId: Arr::get($this->filter, 'location_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'filter.class_type_id' => ['nullable', new Enum(ClassType::class)],
            'filter.coach_user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'filter.is_session' => ['nullable', new BooleanRule],
            'filter.is_visible_in_app' => ['nullable', new BooleanRule],
            'filter.is_active' => ['nullable', new BooleanRule],
            'filter.is_virtual' => ['nullable', new BooleanRule],
            'filter.tag_id' => new TagRule('location', Arr::has($this->filter, 'location_id') ? 'box_facility' : null, Arr::get($this->filter, 'location_id')),
            'per_page' => new PerPageRule(),
        ];
    }
}
