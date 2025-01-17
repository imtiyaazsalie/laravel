<?php

namespace App\Http\Requests\Address;

use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class ListAddressesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'filter.city' => 'nullable|string',
            'filter.state_province_region' => 'nullable|string',
            'filter.postal_code' => 'nullable|string',
            'filter.country_code' => 'nullable|string',
        ];
    }
}
