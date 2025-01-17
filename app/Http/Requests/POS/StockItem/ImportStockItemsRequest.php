<?php

namespace App\Http\Requests\POS\StockItem;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ImportStockItemsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->tenant_id,
            locationId: $this->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'csv_file' => 'required|file|mimetypes:text/csv',
        ];
    }
}
