<?php

namespace App\Http\Requests\Location;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                UserType::SUPER_ADMINISTRATOR,
                UserType::ADMIN,
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'billing_amounts.*.location_id' => 'required|exists:box_facility,box_facility_id',
            'billing_amounts.*.amount' => 'required|numeric',
        ];
    }
}
