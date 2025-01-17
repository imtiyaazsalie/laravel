<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Rules\IbanRule;
use App\Rules\PackageRule;
use App\Rules\PriceRule;
use App\Rules\ProgrammeRule;
use App\Traits\Authorize;
use App\Traits\ValidatesDiscountDetails;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreUserTenantRequest extends FormRequest
{
    use Authorize;
    use ValidatesDiscountDetails;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'member_add',
            tenantId: $this->tenant_id,
            locationId: $this->location_id
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
                    'payment_details.iban' => ['required', new IbanRule($this->input('payment_details.bic'))],
                    'payment_details.bic' => 'required',
                    'payment_details.address' => 'sometimes',
                ]);
            }
        }

        return array_merge($rules, [
            'tenant_id' => [
                'required_with:user_id',
                'exists:boxes,box_id',
                'exists:user_to_box,box_id',
            ],

            'user_id' => 'required|exists:users,user_id',
            'type_id' => ['required', new Enum(UserType::class)],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'location_id' => ['nullable', 'required_if:type_id,'.UserType::GYM_MEMBER->value, 'exists:box_facility,box_facility_id'],
            'programme_id' => ['nullable', 'integer', 'required_if:type_id,'.UserType::GYM_MEMBER->value, new ProgrammeRule(tenantId: $this->tenant_id, active: true)],
            'package_id' => ['nullable', 'integer', new PackageRule($this->tenant_id)],
            'notes' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'member_id' => ['nullable'],

            'contract_details.start_date' => ['sometimes', 'date_format:Y-m-d'],
            'contract_details.end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:contract_details.start_date'],
            'contract_details.file' => ['sometimes', 'file', 'mimes:pdf', 'size:4048'],

            'payment_details' => 'array|required_if:type_id,'.UserType::GYM_MEMBER->value,
            'payment_details.debit_status_id' => 'required_if:type_id,4|in:1,2,4,5,6',
            'payment_details.invoicing_type' => 'required_if:payment_details.debit_status_id,1',
            'payment_details.auto_invoicing_day' => 'required_if:payment_details.invoicing_type,custom_dates',
            'payment_details.auto_invoicing_due_day' => 'required_if:payment_details.invoicing_type,custom_dates',
            'payment_details.debit_day_id' => 'required_if:payment_details.debit_status_id,2|exists:debit_days,debit_day_id',
            'payment_details.upfront_payment_method' => 'required_if:payment_details.debit_status_id,5',
            'payment_details.upfront_payment_amount' => 'required_if:payment_details.debit_status_id,5',
            'payment_details.upfront_payment_period' => 'required_if:payment_details.debit_status_id,5|integer',
            'payment_details.upfront_payment_period_type' => 'required_if:payment_details.debit_status_id,5',
            'payment_details.pro_rated_amount' => ['nullable', new PriceRule()],
            'payment_details.file' => ['nullable', 'file', 'max:4000'],
        ],
            $this->discountDetailsValidationRules(),
        );
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'The user id has already been taken for the tenant_id selected.',
        ];
    }
}
