<?php

namespace App\Http\Requests\Invoice;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class AddInvoiceToDebitBatchRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantAdmins(),
            permission: 'accounts_invoice_actions',
            tenantId: $this->route('userInvoice')->invoice_location->tenant_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'debit_batch_id' => ['nullable', 'integer', 'exists:debit_batches,debit_batch_id'],
        ];
    }
}
