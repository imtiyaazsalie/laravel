<?php

namespace App\Http\Requests\InvoiceItemType;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceItemTypeRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            permission: 'accounts_invoices',
            tenantId: $this->route('invoiceItemType')->tenant_id,
            userTypes: [
                UserType::HEAD_COACH,
                UserType::BOX_ADMIN,
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                Rule::unique('finances_invoice_item_types')->where(function ($query) {
                    return $query->where('name', $this->name)
                        ->where('box_id', $this->route('invoiceItemType')->box_id);
                }),
            ],
        ];
    }
}
