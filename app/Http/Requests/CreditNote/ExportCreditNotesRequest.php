<?php

namespace App\Http\Requests\CreditNote;

use App\Enums\UserType;
use App\Rules\DatesBetweenRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ExportCreditNotesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'accounts_invoices',
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
            'filter.tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'filter.location_id' => 'nullable|integer|exists:box_facility,box_facility_id',
            'filter.between' => ['required', new DatesBetweenRule()],
            'filter.sent_status' => 'nullable|in:sent,unsent',
            'filter.search' => 'nullable|string',
            'per_page' => new PerPageRule(),
        ];
    }
}
