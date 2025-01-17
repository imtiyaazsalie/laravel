<?php

namespace App\Http\Requests\Packages;

use App\Enums\PackageType;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PriceRule;
use App\Rules\TagRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreatePackageRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantAdmins(),
            tenantId: $this->tenant_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'name' => 'required|string|min:3|max:120',
            'description' => 'nullable|string|min:3|max:2500',
            'price' => ['required', new PriceRule],
            'late_cancellation_fee' => 'sometimes|nullable|numeric|min:0',
            'no_show_fee' => 'sometimes|nullable|numeric|min:0',
            'health_provider_price' => ['nullable', new PriceRule],
            'topup_price' => ['nullable', new PriceRule],
            'type' => ['required', new Enum(PackageType::class)],
            'limit' => 'nullable|integer|between:0,9999',
            'default_period' => 'required_with:default_period_interval|integer|digits_between:1,30',
            'default_period_interval' => 'required_with:default_period|in:days,weeks,months,years',
            'is_hidden' => ['required', new BooleanRule()],
            'is_displayed' => ['required', new BooleanRule()],
            'is_display_on_buy_packages' => ['required', new BooleanRule()],
            'tag_ids' => new TagRule('package', 'boxes', $this->tenant_id),
            'priority' => 'nullable|integer',
        ];
    }
}
