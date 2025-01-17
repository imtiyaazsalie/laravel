<?php

namespace App\Http\Requests\GoCardless;

use App\Enums\MandateStatus;
use App\Enums\UserType;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GetDebitOrderMembersRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: $this->input('filter.tenant_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|exists:boxes,box_id',
            'filter.location_id' => 'nullable|exists:box_facility,box_facility_id',
            'mandate_status' => ['nullable', new Enum(MandateStatus::class)],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
