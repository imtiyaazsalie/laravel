<?php

namespace App\Http\Requests\POS\Sales;

use App\Enums\PaymentType;
use App\Enums\UserType;
use App\Models\Location;
use App\Rules\MobileNumberRule;
use App\Rules\PriceRule;
use App\Rules\StockItemRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePosSaleRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $location = Location::findOrFail($this->location_id);

        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            tenantId: $location->tenant_id,
            locationId: $location->getKey(),
            userId: $this->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'sale_payment_type' => ['required', 'string', Rule::in(PaymentType::posTypes())],
            'discount' => ['nullable', new PriceRule()],
            'note' => 'nullable|string',

            'user_id' => ['required_without:non_member', 'integer', 'exists:users,user_id'],

            'non_member' => 'required_without:user_id|array',
            'non_member.name' => 'required_without:user_id|string|min:2|max:255',
            'non_member.email' => 'required_without:user_id|email:rfc,dns',
            'non_member.mobile' => ['string', new MobileNumberRule()],

            'products' => ['required', 'array'],
            'products.*.id' => ['required', new StockItemRule($this->location_id)],
            'products.*.quantity' => ['required', 'numeric', 'min:1'],
        ];
    }
}
