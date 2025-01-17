<?php

namespace App\Http\Requests\User;

use App\Enums\AccountType;
use App\Enums\Gender;
use App\Enums\PackageType;
use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Models\Location;
use App\Models\Package;
use App\Models\Tenant;
use App\Rules\BooleanRule;
use App\Rules\MobileNumberRule;
use App\Rules\ProgrammeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class SignUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $conditionalRules = [];
        $tenant = Tenant::findOrFail($this->input('tenant_id'));
        $package = Package::findOrFail($this->input('package_id'));
        $isLimitedPackage = $package->type === PackageType::LIMITED;

        if ($this->has('user_id')) {
            $conditionalRules = array_merge($conditionalRules, [
                'user_id' => 'required|exists:users,user_id',
            ]);
        } else {
            $conditionalRules = array_merge($conditionalRules, [
                'name' => 'required|string|min:1|max:120',
                'surname' => 'required|string|min:1|max:120',
                'email' => 'required|email:rfc,dns',
                'mobile' => ['nullable', new MobileNumberRule()],
                'gender_id' => ['nullable', new Enum(Gender::class)],
                'id_number' => ['nullable'],
                'address' => ['nullable'],
                'date_of_birth' => 'nullable|date|before:today',
                'emergency_contact_name' => 'nullable|string|min:1|max:120',
                'emergency_contact_mobile' => ['nullable', new MobileNumberRule()],
                'password' => [auth()->check() ? 'nullable' : 'required', Password::defaults()],
            ]);
        }

        if (! $isLimitedPackage && ! $this->has('payment_details.debit_status_id')) {
            $conditionalRules = array_merge($conditionalRules, [
                'payment_details.debit_status_id' => ['required', 'integer', new Enum(UserDebitStatus::class)],
            ]);
        }

        if ($this->input('payment_details.debit_status_id') == UserDebitStatus::DEBIT_ORDER->value) {
            $conditionalRules = array_merge($conditionalRules, $this->getDebitOrderRules());
        }

        // Check if the box makes use of waivers
        if ($tenant->signup_use_contract_and_waivers) {
            $conditionalRules = array_merge($conditionalRules, [
                'terms_and_conditions_for_contract' => ['required', 'accepted', new BooleanRule()],
                'terms_and_conditions_for_waiver' => ['required', 'accepted', new BooleanRule()],
            ]);
        }

        return array_merge($conditionalRules, [
            'tenant_id' => 'required|exists:boxes,box_id',
            'location_id' => 'required|exists:box_facility,box_facility_id',
            'package_id' => 'required|exists:packages,package_id',
            'programme_id' => ['required', 'exists:programmes,id',  new ProgrammeRule(tenantId: $tenant->getKey(), active: true)],
            'payment_details' => 'nullable',
            'payment_details.debit_day_id' => 'required_if:payment_details.debit_status_id,2|exists:debit_days,debit_day_id',
        ]);
    }

    public function getDebitOrderRules(): array
    {
        $location = Location::findOrFail($this->input('location_id'));

        if (in_array($location->paymentGateway->getKey(), [PaymentGateway::GO_CARDLESS->value, PaymentGateway::STRIPE_CONNECT->value])) {
            return [];
        }

        if ($location->paymentGateway->getKey() === PaymentGateway::SEPA->value) {
            return [
                'payment_details.account_holder_name' => 'required',
                'payment_details.iban' => ['required'],
                'payment_details.bic' => 'required',
                'payment_details.address' => 'sometimes',
            ];
        }

        return [
            'payment_details.bank_id' => ['required', 'integer', 'exists:banks,bank_id'],
            'payment_details.account_type_id' => ['required', 'integer', new Enum(AccountType::class)],
            'payment_details.account_number' => 'required',
            'payment_details.account_holder_name' => 'required',
            'payment_details.branch_code' => 'nullable',
        ];
    }
}
