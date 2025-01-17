<?php

namespace App\Http\Requests;

use App\Models\LocationInvoice;
use App\Models\LocationPayment;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationPaymentCreateRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $invoice = LocationInvoice::with('location')->findOrFail($this->facility_invoice_id);

        return $this->canOperate(
            tenantId: $invoice->location->tenant_id,
            locationId: $invoice->location_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'facility_invoice_id' => 'required|exists:finance_facility_invoices,facility_invoice_id',
            'date' => 'required',
            'type' => [
                'required',
                Rule::in(LocationPayment::select('type')->distinct()->pluck('type')),
            ],
            'reference' => 'sometimes',
            'amount' => 'required|regex:/^\d+\.\d{0,2}$/',
        ];
    }
}
