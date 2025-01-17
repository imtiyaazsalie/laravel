<?php

namespace App\Http\Requests\Tags;

use App\Enums\TagType;
use App\Enums\UserType;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class ListTagsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @throws ValidationException
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->input('filter.tenant_id'),
            locationId: $this->input('filter.location_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.type' => ['required', new Enum(TagType::class)],
            'filter.tenant_id' => ['required_without:filter.location_id', 'integer', 'exists:boxes,box_id'],
            'filter.location_id' => ['required_without:filter.tenant_id', 'integer', 'exists:box_facility,box_facility_id'],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
