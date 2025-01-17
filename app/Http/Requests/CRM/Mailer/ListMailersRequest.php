<?php

namespace App\Http\Requests\CRM\Mailer;

use App\Enums\MailerType;
use App\Enums\UserType;
use App\Rules\EnumRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListMailersRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: Arr::get($this->filter, 'tenant_id'),
            locationId: Arr::get($this->filter, 'location_id'),
            permissions: [
                'default' => ['communication_center'],
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'filter.created_by_id' => 'nullable|integer|exists:users,user_id',
            'filter.type' => ['nullable', new EnumRule(MailerType::class)],
            'filter.status' => ['nullable', 'string'],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
