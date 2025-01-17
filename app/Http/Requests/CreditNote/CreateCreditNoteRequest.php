<?php

namespace App\Http\Requests\CreditNote;

use App\Enums\UserType;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateCreditNoteRequest extends FormRequest
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
            tenantId: $this->tenant_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'description' => 'nullable|string',
            'due_on' => 'required|date',
            'amount' => ['required', new PriceRule()],
        ];
    }
}
