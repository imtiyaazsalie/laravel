<?php

namespace App\Http\Requests\Location;

use App\Enums\PaymentGateway;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\ImageRule;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use App\Rules\PhoneNumberRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateLocationRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                UserType::SUPER_ADMINISTRATOR,
                UserType::ADMIN,
            ],
            tenantId: $this->tenant_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'name' => 'required|string|min:3|max:255',
            'prefix' => 'nullable|string|min:1|max:12',
            'business_name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|min:3|max:255',
            'address' => 'nullable|string|min:3|max:255',
            'latitude' => ['nullable', new LatitudeRule()],
            'longitude' => ['nullable', new LongitudeRule()],
            'phone_number' => ['nullable', new PhoneNumberRule()],
            'image_one' => ['nullable', new ImageRule()],
            'image_two' => ['nullable', new ImageRule()],
            'image_three' => ['nullable', new ImageRule()],
            'image_four' => ['nullable', new ImageRule()],
            'timezone_id' => 'nullable|integer|exists:timezones,timezone_id',
            'category_id' => 'required|integer|exists:box_facility_categories,box_facility_category_id',
            'can_debit' => ['required', new BooleanRule],
            'billing_payment_gateway_id' => ['required', new Enum(PaymentGateway::class)],
            'payment_gateway_id' => ['required', new Enum(PaymentGateway::class)],
            'sage_merchant_account_number' => ['required_if:payment_gateway_id,4', 'alpha_dash:ascii'],
            'sage_account_service_key' => ['required_if:payment_gateway_id,4', 'alpha_dash:ascii'],
            'sage_debit_order_service_key' => ['required_if:payment_gateway_id,4', 'alpha_dash:ascii'],
            'three_peaks_dev_id' => 'required_if:payment_gateway_id,3',
            'three_peaks_dev_token' => 'required_if:payment_gateway_id,3',
            'three_peaks_cref' => 'required_if:payment_gateway_id,3',
            'health_provider_ids' => 'nullable|array',
            'health_provider_ids.*' => 'integer|exists:health_providers,id',
            'amenity_ids' => 'nullable|array',
            'amenity_ids.*' => 'integer|exists:amenities,id',
        ];
    }
}
