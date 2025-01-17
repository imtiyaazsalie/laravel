<?php

namespace App\Http\Requests\Settings;

use App\Enums\DebitOrderSetting;
use App\Enums\UserType;
use App\Rules\EnumRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantFinanceDebitOrderSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            permission: 'settings',
            tenantId: $this->route('tenant')->tenant_id,
            userTypes: UserType::locationAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'debit_order_settings' => 'required|array',
            'debit_order_settings.*.id' => 'required|exists:debit_order_settings,debit_order_settings_id',
            'debit_order_settings.*.invoice_due_on_date' => new EnumRule(DebitOrderSetting::class),
        ];
    }
}
