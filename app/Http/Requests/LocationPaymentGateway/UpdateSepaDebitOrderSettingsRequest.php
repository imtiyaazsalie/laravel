<?php

namespace App\Http\Requests\LocationPaymentGateway;

use App\Enums\UserType;
use App\Models\LocationPaymentGateway;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSepaDebitOrderSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        /** @var LocationPaymentGateway $locationPaymentGateway */
        $locationPaymentGateway = $this->route('locationPaymentGateway');

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'settings',
            tenantId: $locationPaymentGateway->location->tenant_id,
            locationId: $locationPaymentGateway->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'creditor_id' => 'required|string',
            'creditor_iban' => 'required|string',
            'creditor_bic' => 'required|string',
        ];
    }
}
