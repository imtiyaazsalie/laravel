<?php

namespace App\Http\Requests\Invoice;

use App\Enums\InvoiceStatus;
use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\DatesBetweenRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListInvoicesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: Tenant::query()->findOrFail($this->input('filter.tenant_id'))->getKey(),
            permissions: [
                'default' => ['accounts_invoices'],
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
            'filter.type' => 'required|in:nonMemberInvoices,leadMemberInvoices,athleteInvoices',
            'filter.payment_type' => 'nullable|in:debitOrderInvoices,otherInvoices',
            'filter.package_id' => 'nullable|integer|exists:packages,package_id',
            'filter.between' => ['required', new DatesBetweenRule()],
            'filter.sent_status' => 'nullable|in:sent,unsent',
            'filter.status' => ['nullable', new Enum(InvoiceStatus::class)],
            'filter.search' => 'nullable|string|min:3|max:120',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
