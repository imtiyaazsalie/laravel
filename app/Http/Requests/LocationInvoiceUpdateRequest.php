<?php

namespace App\Http\Requests;

use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class LocationInvoiceUpdateRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('invoice')->location->tenant_id,
            locationId: $this->route('invoice')->location_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'description' => 'sometimes|string',
            'due_on' => 'required|date',
            'line_items' => 'required|array',
            'line_items.*.description' => 'required|string',
            'line_items.*.unit_price' => 'required|regex:/^\d+\.\d{0,2}$/',
            'line_items.*.quantity' => 'required|numeric',
            'line_items.*.discriminator' => 'required|string',
        ];
    }
}
