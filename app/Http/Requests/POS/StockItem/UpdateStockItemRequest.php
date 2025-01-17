<?php

namespace App\Http\Requests\POS\StockItem;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\ImageRule;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStockItemRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->route('stockItem')->location->tenant_id,
            locationId: $this->route('stockItem')->location_id
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
