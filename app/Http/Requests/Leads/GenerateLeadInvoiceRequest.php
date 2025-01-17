<?php

namespace App\Http\Requests\Leads;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateLeadInvoiceRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $leadMember = $this->route('leadMember');

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $leadMember->location->tenant_id,
            locationId: $leadMember->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'due_on' => 'required|date|after:yesterday',
            'is_send' => ['required', new BooleanRule()],
            'amount' => ['required', new PriceRule()],
        ];
    }
}
