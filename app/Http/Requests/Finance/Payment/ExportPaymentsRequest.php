<?php

namespace App\Http\Requests\Finance\Payment;

use App\Enums\InvoicePaymentType;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\Tenant;
use App\Rules\DatesBetweenRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ExportPaymentsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $location = Location::query()->findOrFail($this->input('filter.location_id'));

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'accounts_payments',
            tenantId: Tenant::query()->findOrFail($this->input('filter.tenant_id'))->getKey(),
            locationId: $location->getKey()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.invoice_member_type' => 'required|in:leadMemberInvoicePayments,athleteInvoicePayments',
            'filter.location_id' => ['required', 'integer', 'exists:box_facility,box_facility_id'],
            'filter.tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'filter.user_id' => ['sometimes', 'integer', 'exists:users,user_id'],
            'filter.type' => ['nullable', new Enum(InvoicePaymentType::class)],
            'filter.between' => ['required', new DatesBetweenRule()],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
