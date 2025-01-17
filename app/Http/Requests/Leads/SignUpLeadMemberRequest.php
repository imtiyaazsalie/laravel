<?php

namespace App\Http\Requests\Leads;

use App\Enums\Gender;
use App\Models\Tenant;
use App\Rules\BooleanRule;
use App\Rules\MobileNumberRule;
use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SignUpLeadMemberRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $tenant = Tenant::find($this->input('tenant_id'));

        $rules = [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'name' => 'required|string|min:1|max:120',
            'surname' => 'required|string|min:1|max:120',
            'email' => 'required|email:rfc,dns',
            'mobile' => ['nullable', new MobileNumberRule()],
            'gender_id' => ['nullable', new Enum(Gender::class)],
            'notes' => 'nullable|string',
            'is_request_demo' => ['nullable', new BooleanRule()],
        ];

        if ($this->input('is_request_demo') && $tenant->signup_use_contract_and_waivers) {
            $isTermsAndConditionsForWaiver = ($this->input('terms_and_conditions_for_waiver') === true || $this->input('terms_and_conditions_for_waiver') === 'true');

            if (! $isTermsAndConditionsForWaiver) {
                $rules['terms_and_conditions_for_waiver'] = 'required|boolean';
            }
        }

        if (in_array(config('app.env'), ['development', 'uat', 'staging', 'production'])) {
            if (! $this->input('g_recaptcha_response') || $this->input('g_recaptcha_response') == '') {
                $rules['g_recaptcha_response'] = 'required';
            } else {
                // call curl to POST request
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['secret' => '6LcbM88ZAAAAAPh4gTzCfvJVPU9HW2p-5hZYKKow', 'response' => $this->input('g_recaptcha_response')]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_close($ch);
                $arrResponse = json_decode($response, true);

                if ($arrResponse['success'] === false) {
                    abort('400', 'RECAPTCHA Failed');
                }
            }
        }

        return $rules;
    }
}
