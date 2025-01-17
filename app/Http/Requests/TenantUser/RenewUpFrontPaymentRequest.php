<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\InvoicePaymentType;
use App\Enums\UserType;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RenewUpFrontPaymentRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'member_manage_package',
            tenantId: $this->route('userTenant')->tenant_id,
            userId: $this->route('userTenant')->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', new Enum(InvoicePaymentType::class)],
            'amount' => ['required', new PriceRule],
            'upfront_payment_period' => 'required|numeric|gt:0',
            'upfront_payment_period_type' => 'required|in:days,weeks,months,years',
        ];
    }
}
