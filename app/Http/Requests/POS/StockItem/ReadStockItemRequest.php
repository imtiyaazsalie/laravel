<?php

namespace App\Http\Requests\POS\StockItem;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ReadStockItemRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->route('stockItem')->location->tenant_id,
            locationId: $this->route('stockItem')->location_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
