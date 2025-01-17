<?php

namespace App\Http\Requests\LocationPaymentGateway;

use App\Enums\UserType;
use App\Models\LocationPaymentGateway;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThreePeaksDebitOrderSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
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
            'dev_id' => 'required|string',
            'dev_token' => 'required|string',
            'cref' => 'required|string',
        ];
    }
}
