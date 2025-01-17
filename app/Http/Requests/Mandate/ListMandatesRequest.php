<?php

namespace App\Http\Requests\Mandate;

use App\Enums\MandateStatus;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListMandatesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->input('filter.tenant_id'),
            locationId: $this->input('filter.location_id'),
            userId: $this->input('filter.user_id'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.location_id' => 'nullable|required_without:filter.tenant_id|integer|exists:box_facility,box_facility_id',
            'filter.user_id' => 'nullable|integer|exists:users,user_id',
            'filter.status' => ['nullable', new Enum(MandateStatus::class)],
            'filter.is_debit_order_members_only' => new BooleanRule(),
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
