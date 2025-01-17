<?php

namespace App\Http\Requests\Invoice;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class SendPaymentNoticesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: Tenant::query()->findOrFail($this->input('tenant_id'))->getKey(),
            permissions: [
                'default' => ['accounts_invoice_actions'],
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'exists:finance_invoices,invoice_id'],
            'location_payment_gateway_id' => ['required', 'exists:box_facility_to_payment_gateway,facility_to_payment_gateway_id'],
            'is_immediate_payment' => ['required', new BooleanRule()],
        ];
    }
}
