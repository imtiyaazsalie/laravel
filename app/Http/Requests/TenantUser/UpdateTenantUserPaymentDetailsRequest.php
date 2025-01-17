<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserType;
use App\Traits\Authorize;
use App\Traits\ValidatesDiscountDetails;
use App\Traits\ValidatesPaymentDetails;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateTenantUserPaymentDetailsRequest extends FormRequest
{
    use Authorize;
    use ValidatesDiscountDetails;
    use ValidatesPaymentDetails;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            permission: 'member_manage_package',
            tenantId: $this->route('userTenant')->tenant_id,
            allowMember: true,
            userId: $this->route('userTenant')->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $validateSepaDetails = Arr::get($this->payment_details, 'debit_status_id') === UserDebitStatus::DEBIT_ORDER->value
            && in_array($this->route('userTenant')->currentLocationUser?->location->paymentGateway->getKey(), [
                PaymentGateway::SEPA->value,
            ]);

        $validateAccountDetails = Arr::get($this->payment_details, 'debit_status_id') === UserDebitStatus::DEBIT_ORDER->value
            && in_array($this->route('userTenant')->currentLocationUser?->location->paymentGateway->getKey(), [
                PaymentGateway::THREE_PEAKS->value, PaymentGateway::SAGE_PAY_V2->value, PaymentGateway::SAGE_PAY_V3->value,
            ]);

        return array_merge(
            $this->paymentDetailsValidationRules(
                validateAccountDetails: $validateAccountDetails,
                validateSepaDetails: $validateSepaDetails,
            ),
            $this->discountDetailsValidationRules()
        );
    }
}
