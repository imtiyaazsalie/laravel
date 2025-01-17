<?php

namespace App\Http\Requests\Address;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CreateAddressRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $model = $this->route('type')->getModelClass()::findOrFail($this->route('id'));

        return $this->canOperate(
            userTypes: UserType::allRoles(),
            tenantId: $model->tenant_id,
            locationId: $model->location_id
        );
    }

    protected function prepareForValidation()
    {
        if ($this->has('structured_address.complex_details')) {
            $fullAddress = Str::of($this->input('full_address'))->prepend($this->input('structured_address.complex_details').', ');

            $this->merge(['full_address' => $fullAddress->toString()]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'full_address' => 'required|string',
            'coordinates' => 'required|array',
            'coordinates.longitude' => 'required',
            'coordinates.latitude' => 'required',
            'structured_address' => 'required|array',
            'structured_address.complex_details' => 'sometimes|string',
            'structured_address.address_number' => 'sometimes|string',
            'structured_address.street' => 'sometimes|string',
            'structured_address.block' => 'sometimes|string',
            'structured_address.region' => 'sometimes|string',
            'structured_address.postcode' => 'sometimes|string',
            'structured_address.locality' => 'sometimes|string',
            'structured_address.neighborhood' => 'sometimes|string',
            'structured_address.country' => 'sometimes|string',
        ];
    }
}
