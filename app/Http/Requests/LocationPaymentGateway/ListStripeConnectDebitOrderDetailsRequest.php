<?php

namespace App\Http\Requests\LocationPaymentGateway;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListStripeConnectDebitOrderDetailsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'settings',
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
            'location_id' => 'nullable|integer|exists:box_facility,box_facility_id',
        ];
    }
}
