<?php

namespace App\Http\Requests\Invoice;

use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\BooleanRule;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateInvoiceRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: Tenant::query()->findOrFail($this->input('tenant_id'))->getKey(),
            userId: $this->input('user_id'),
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
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'description' => 'nullable|string|min:3|max: 255',
            'due_on' => 'required|date',
            'status' => ['required', new Enum(InvoiceStatus::class)],
            'payment_type' => ['sometimes', new Enum(InvoicePaymentType::class)],
            'payment_reference' => 'nullable|string|min:3|max:255',
            'line_items' => 'array',
            'line_items.*.description' => 'required|string',
            'line_items.*.unit_price' => ['required', new PriceRule(-100000000.00)],
            'line_items.*.quantity' => 'required|int',
            'line_items.*.discriminator' => 'required|string',
            'is_send' => ['sometimes', new BooleanRule()],
        ];
    }
}
