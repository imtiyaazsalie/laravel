<?php

namespace App\Http\Requests\Tags;

use App\Enums\TagType;
use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateTagRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->tenant_id,
            locationId: $this->location_id,
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:120',
            'type' => ['required', new Enum(TagType::class)],
            'tenant_id' => ['nullable', 'integer', 'exists:boxes,box_id'],
            'location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
        ];
    }
}
