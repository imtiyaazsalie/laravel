<?php

namespace App\Http\Requests\Packages;

use App\Enums\PackageType;
use App\Rules\BooleanRule;
use App\Rules\EnumRule;
use App\Rules\PerPageRule;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListPackageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required_without:filter.location_id|integer|exists:boxes,box_id',
            'filter.location_id' => 'required_without:filter.tenant_id|integer|exists:box_facility,box_facility_id',
            'filter.package_id' => 'nullable|integer|exists:packages,package_id',
            'filter.type_id' => ['nullable', new EnumRule(PackageType::class)],
            'filter.is_active' => ['nullable', new BooleanRule],
            'filter.is_hidden' => ['nullable', new BooleanRule],
            'filter.is_for_sign_up' => ['nullable', new BooleanRule],
            'filter.is_for_buy_packages' => ['nullable', new BooleanRule],
            'filter.has_health_provider_price' => ['nullable', new BooleanRule],
            'filter.tag_ids' => 'nullable|exists:tags,id',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
