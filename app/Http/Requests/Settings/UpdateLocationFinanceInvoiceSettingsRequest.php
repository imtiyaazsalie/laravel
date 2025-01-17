<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\ImageRule;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationFinanceInvoiceSettingsRequest extends FormRequest
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
            tenantId: $this->route('location')->tenant_id,
            locationId: $this->route('location')->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'vat_number' => ['nullable', 'string'],
            'vat_percent' => ['nullable', new PriceRule(min: 0.00, max: 100.00)],
            'invoice_information' => ['nullable', 'string'],
            'show_invoice_totals' => ['nullable', new BooleanRule()],
            'logo' => ['nullable', new ImageRule()],
        ];
    }
}
