<?php

namespace App\Http\Requests\LocationPaymentGateway;

use App\Enums\UserType;
use App\Services\PaymentGateways\NetcashService;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNetcashDebitOrderSettingsRequest extends FormRequest
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
            tenantId: $this->route('locationPaymentGateway')->location->tenant_id,
            locationId: $this->route('locationPaymentGateway')->location_id
        );
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $validateResult = (new NetcashService())->validateServiceKeys($validator->safe()->merchant_account_number, [
                1 => $validator->safe()->account_service_key,
                5 => $validator->safe()->debit_order_service_key,
            ]);

            if (! $validateResult['success']) {
                $validator->errors()->add('netcash_account_details', $validateResult['message'] ?? 'Debit order settings could not be saved. Please check your information to make sure everything is valid or contact your Sagepay account manager for assistance.');
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'merchant_account_number' => ['required', 'alpha_dash:ascii'],
            'account_service_key' => ['required', 'alpha_dash:ascii'],
            'debit_order_service_key' => ['required', 'alpha_dash:ascii'],
        ];
    }
}
