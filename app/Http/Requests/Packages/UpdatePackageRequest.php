<?php

namespace App\Http\Requests\Packages;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PriceRule;
use App\Rules\TagRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::tenantAdmins(),
            tenantId: $this->route('package')->tenant_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|min:3|max:120',
            'description' => 'nullable|string|min:3|max:500',
            'is_active' => ['nullable', new BooleanRule()],
            'price' => ['sometimes', new PriceRule()],
            'health_provider_price' => ['nullable', new PriceRule()],
            'late_cancellation_fee' => 'sometimes|nullable|numeric|min:0',
            'no_show_fee' => 'sometimes|nullable|numeric|min:0',
            'topup_price' => ['nullable', new PriceRule()],
            'limit' => 'nullable|integer|between:0,9999',
            'default_period' => 'nullable|digits_between:0,90',
            'default_period_interval' => 'required_with:default_period,in:days,weeks,months,years',
            'is_hidden' => ['sometimes', new BooleanRule()],
            'is_displayed' => ['sometimes', new BooleanRule()],
            'is_display_on_buy_packages' => ['sometimes', new BooleanRule()],
            'tag_ids' => ['sometimes', 'nullable', new TagRule('package', 'boxes', $this->route('package')->tenant_id)],
            'priority' => 'nullable|integer',
        ];
    }
}
