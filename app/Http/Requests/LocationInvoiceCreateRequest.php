<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class LocationInvoiceCreateRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $location = Location::findOrFail($this->location);

        return $this->canOperate(
            tenantId: $location->tenant_id,
            locationId: $this->location_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'description' => 'sometimes|string',
            'due_on' => 'required|date',
            'status' => 'required|string',
            'payment_reference' => 'sometimes|string',
            'line_items' => 'required|array',
            'line_items.*.description' => 'required|string',
            'line_items.*.unit_price' => 'required|regex:/^\d+\.\d{0,2}$/',
            'line_items.*.quantity' => 'required|numeric',
            'line_items.*.discriminator' => 'required|string',
            'is_send' => ['required', new BooleanRule()],
        ];
    }
}
