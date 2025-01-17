<?php

namespace App\Http\Requests\Invoice;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->route('userInvoice')->invoice_location->tenant_id,
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
            'description' => 'nullable|string',
            'due_on' => 'required|date',
            'line_items' => 'sometimes|array',
            'credit_amount' => ['nullable', 'integer'],
        ];
    }
}
