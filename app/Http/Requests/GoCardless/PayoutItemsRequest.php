<?php

namespace App\Http\Requests\GoCardless;

use App\Enums\UserType;
use App\Models\Location;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class PayoutItemsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $location = Location::findOrFail($this->input('location_id'));

        return $this->canOperate(
            userTypes: UserType::tenantAdmins(),
            tenantId: $location->tenant_id,
            locationId: $location->getKey()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_id' => 'required|exists:box_facility,box_facility_id',
            'payout_id' => 'required',
            'limit' => 'nullable',
            'after' => 'nullable',
            'before' => 'nullable',
        ];
    }
}
