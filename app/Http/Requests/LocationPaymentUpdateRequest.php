<?php

namespace App\Http\Requests;

use App\Models\LocationPayment;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationPaymentUpdateRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('payment')->invoice->location->tenant_id,
            locationId: $this->route('payment')->invoice->location_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
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
