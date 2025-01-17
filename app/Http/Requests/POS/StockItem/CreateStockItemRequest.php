<?php

namespace App\Http\Requests\POS\StockItem;

use App\Enums\UserType;
use App\Models\Location;
use App\Rules\BooleanRule;
use App\Rules\ImageRule;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class CreateStockItemRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $location = Location::findOrFail(Arr::get($this->location_ids, 0));

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $location->tenant_id,
            locationId: $location->getKey()
        );
    }

    public function prepareForValidation(): void
    {
        if ($this->has('has_limited_stock')) {
            $this->merge([
                'stock_level' => $this->get('has_limited_stock') ? $this->get('stock_level') : -1,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_ids' => ['required', 'array', 'exists:box_facility,box_facility_id'],
            'name' => 'required|string|min:3|max:255',
            'cost_price' => ['required', new PriceRule()],
            'selling_price' => ['required', new PriceRule()],
            'vat' => ['nullable', new PriceRule(min: 0.00, max: 100.00)],
            'sku' => 'nullable|string',
            'has_limited_stock' => ['required', new BooleanRule()],
            'stock_level' => 'nullable|integer',
            'description' => 'nullable|string',
            'category' => 'required|string',
            'image' => ['nullable', new ImageRule()],
        ];
    }
}
