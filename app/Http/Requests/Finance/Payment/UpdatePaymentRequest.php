<?php

namespace App\Http\Requests\Finance\Payment;

use App\Enums\InvoicePaymentType;
use App\Enums\UserType;
use App\Rules\PriceRule;
use App\Rules\TagRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePaymentRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'accounts_payments',
            tenantId: $this->route('payment')->invoice->invoice_location->tenant_id,
            locationId: $this->route('payment')->invoice->invoice_location->getKey()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'type' => ['required', new Enum(InvoicePaymentType::class)],
            'amount' => ['required', new PriceRule()],
            'reference' => 'nullable|string',
            'tag_id' => ['sometimes', 'nullable', new TagRule('payment_processor')],
        ];
    }
}
