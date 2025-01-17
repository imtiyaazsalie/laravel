<?php

namespace App\Http\Requests\Leads;

use App\Models\Tenant;
use App\Rules\BooleanRule;
use App\Rules\MobileNumberRule;
use App\Traits\Authorize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateDropInRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [];

        $tenant = Tenant::findOrFail($this->tenant_id);

        // Check if the box makes use of waivers
        if ($tenant->signup_use_contract_and_waivers) {
            $rules = ['terms_and_conditions' => ['required', 'accepted', new BooleanRule()]];
        }

        return array_merge($rules, [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'package_id' => 'required|integer|exists:packages,package_id',
            'name' => 'required|string|min:1|max:120',
            'surname' => 'required|string|min:1|max:120',
            'email' => 'required|email:rfc,dns',
            'mobile' => ['nullable', new MobileNumberRule()],
            'notes' => 'nullable|string',
        ]);
    }
}
