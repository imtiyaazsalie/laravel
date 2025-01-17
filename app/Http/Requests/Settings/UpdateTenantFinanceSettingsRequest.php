<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantFinanceSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'settings',
            tenantId: $this->route('tenant')->tenant_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'member_billing_currency' => 'required|exists:currencies,currency_id',
            'deactivate_member_on_contract_end' => ['required', new BooleanRule()],

            'cash_member_invoice_generation_day' => 'nullable',
            'cash_member_invoice_due_day' => 'required_with:cash_member_invoice_generation_day',
            'cash_member_invoice_strategy' => ['required_with:cash_member_invoice_generation_day|string|in:generate_only,generate_and_send'],
        ];
    }
}
