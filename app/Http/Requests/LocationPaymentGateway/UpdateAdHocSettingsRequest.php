<?php

namespace App\Http\Requests\LocationPaymentGateway;

use App\Enums\PaymentGateway;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Services\PaymentGateways\NetcashService;
use App\Services\PaymentGateways\PaystackService;
use App\Traits\Authorize;
use Exception;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAdHocSettingsRequest extends FormRequest
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $paymentGatewayId = $this->route('locationPaymentGateway')->payment_gateway_id;

            if ($paymentGatewayId === PaymentGateway::SAGE_PAY_V3->value && config('app.env') === 'production') {
                $validateResult = (new NetcashService())->validateServiceKeys($validator->safe()->merchant_account_number, [
                    14 => $validator->safe()->pay_now_service_key,
                ]);

                if (! $validateResult['success']) {
                    $validator->errors()->add('account_details', 'Account is not valid. Please make sure that your account details are correct.');
                }
            } elseif ($paymentGatewayId === PaymentGateway::PAYSTACK->value && $validator->safe()->has('document_number')) {
                $accountName = $validator->safe()->account_name;
                $accountNumber = $validator->safe()->account_number;
                $accountType = $validator->safe()->account_type;
                $bankCode = $validator->safe()->bank_code;
                $documentType = $validator->safe()->document_type;
                $documentNumber = $validator->safe()->document_number;

                try {
                    $validationResults = (new PaystackService())->validateAccount($accountName, $accountNumber, $accountType, $bankCode, $documentType, $documentNumber);

                    if (! $validationResults['verified']) {
                        $validator->errors()->add('account_details', 'Account is not valid. Please make sure that your account details are correct.');
                    }
                } catch (Exception $exception) {
                    $validator->errors()->add('account_details', $exception->getMessage());
                }
            }
        });
    }

    public function prepareForValidation(): void
    {
        $this->merge(['payment_gateway_id' => $this->route('locationPaymentGateway')->payment_gateway_id]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'merchant_account_number' => ['required_if:payment_gateway_id,4', 'alpha_dash:ascii'],
            'pay_now_service_key' => ['required_if:payment_gateway_id,4', 'alpha_dash:ascii'],
            'secret_key' => 'required_if:payment_gateway_id,6|string',
            'public_key' => 'required_if:payment_gateway_id,6|string',

            'account_number' => 'required_if:payment_gateway_id,7|string',
            'bank_code' => 'required_if:payment_gateway_id,7|string',

            'document_number' => 'prohibited_unless:payment_gateway_id,7|string',
            'account_name' => 'prohibited_unless:payment_gateway_id,7|string',
            'account_type' => 'prohibited_unless:payment_gateway_id,7|in:personal,business',
            'document_type' => 'prohibited_unless:payment_gateway_id,7|in:identityNumber,passportNumber,businessRegistrationNumber',

            'notify_email_address' => 'nullable|email:rfc,dns',
            'is_for_sign_up' => ['required', 'nullable', new BooleanRule()],
        ];
    }
}
