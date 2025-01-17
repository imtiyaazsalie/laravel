<?php

namespace App\Http\Requests\StripeConnect;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class DeleteSetupIntentRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->route('financePaymentToken')->location->tenant_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
