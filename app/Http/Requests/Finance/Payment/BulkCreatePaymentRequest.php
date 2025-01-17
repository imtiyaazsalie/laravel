<?php

namespace App\Http\Requests\Finance\Payment;

use App\Enums\InvoicePaymentType;
use App\Enums\UserType;
use App\Models\UserInvoice;
use App\Rules\TagRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Enum;

class BulkCreatePaymentRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        //TODO: Improve this method of checking the tenant and location ID for multiple invoices.
        $invoice = UserInvoice::findOrFail(Arr::get($this->invoice_ids, 0));

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'accounts_payments',
            tenantId: $invoice->invoice_location->tenant_id,
            locationId: $invoice->invoice_location->getKey()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        //TODO: Improve this method of checking the tenant ID for tags on multiple invoices.
        $invoice = UserInvoice::findOrFail(Arr::get($this->invoice_ids, 0));

        return [
            'invoice_ids' => ['required', 'array', 'exists:finance_invoices,invoice_id'],
            'date' => 'required|date',
            'type' => ['required', new Enum(InvoicePaymentType::class)],
            'reference' => 'nullable|string',
            'tag_id' => ['nullable', new TagRule('payment_processor', 'boxes', $invoice->invoice_location->tenant_id)],
        ];
    }
}
