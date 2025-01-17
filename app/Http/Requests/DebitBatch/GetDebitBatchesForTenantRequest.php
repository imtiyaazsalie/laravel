<?php

namespace App\Http\Requests\DebitBatch;

use App\Enums\PaymentGateway;
use App\Enums\UserType;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GetDebitBatchesForTenantRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.date' => 'required|date|date_format:Y-m-d',
            'filter.region_id' => 'nullable|integer|exists:regions,region_id',
            'filter.location_id' => 'nullable|integer|exists:box_facility,box_facility_id',
            'filter.payment_gateway_id' => ['nullable', new Enum(PaymentGateway::class)],
            'search' => 'nullable|string|min:2|max:120',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
