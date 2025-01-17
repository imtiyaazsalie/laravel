<?php

namespace App\Http\Requests\UserPackage;

use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\Package;
use App\Rules\BooleanRule;
use App\Rules\IbanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class BuyUserPackageRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $package = Package::findOrFail($this->package_id);

        return $this->canOperate(
            userTypes: UserType::GYM_MEMBER,
            tenantId: $package->tenant_id,
            allowMember: true,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [];

        if ($this->input('type_id') === UserType::GYM_MEMBER->value && $this->input('payment_details.debit_status_id') === UserDebitStatus::DEBIT_ORDER->value) {
            $location = Location::find($this->location_id);

            if (in_array($location->payment_gateway_id, [PaymentGateway::THREE_PEAKS->value, PaymentGateway::SAGE_PAY_V2->value, PaymentGateway::SAGE_PAY_V3->value])) {
                $rules = array_merge($rules, [
                    'payment_details.bank_id' => ['required', 'integer', 'exists:banks,bank_id'],
                    'payment_details.account_type_id' => ['required', 'integer', 'exists:account_types,account_type_id'],
                    'payment_details.account_number' => 'required',
                    'payment_details.account_holder_name' => 'required',
                ]);
            } elseif ($location->payment_gateway_id === PaymentGateway::SEPA->value) {
                $rules = array_merge($rules, [
                    'payment_details.account_holder_name' => 'required',
                    'payment_details.bic' => 'required',
                    'payment_details.iban' => ['required', new IbanRule($this->input('payment_details.bic'))],
                    'payment_details.address' => 'sometimes',
                ]);
            }
        }

        return array_merge($rules, [
            'payment_details' => 'array|required_if:type_id,'.UserType::GYM_MEMBER->value,
            'payment_details.debit_status_id' => 'required_if:type_id,4|in:1,2,4,5',
            'payment_details.invoicing_type' => 'required_if:payment_details.debit_status_id,1',
            'payment_details.auto_invoicing_day' => 'required_if:payment_details.invoicing_type,custom_dates',
            'payment_details.auto_invoicing_due_day' => 'required_if:payment_details.invoicing_type,custom_dates',
            'payment_details.debit_day_id' => 'required_if:payment_details.debit_status_id,2|exists:debit_days,debit_day_id',
            'payment_details.upfront_payment_method' => 'required_if:payment_details.debit_status_id,5',
            'payment_details.upfront_payment_amount' => 'required_if:payment_details.debit_status_id,5',
            'payment_details.upfront_payment_period' => 'required_if:payment_details.debit_status_id,5|integer',
            'payment_details.upfront_payment_period_type' => 'required_if:payment_details.debit_status_id,5',
            'payment_details.method' => ['nullable', new Enum(UserDebitStatus::class)],
            'package_id' => 'required|integer|exists:packages,package_id',
            'terms_and_conditions_for_waiver' => ['sometimes', new BooleanRule()],
            'terms_and_conditions_for_contract' => ['sometimes', new BooleanRule()],
        ]);
    }
}
