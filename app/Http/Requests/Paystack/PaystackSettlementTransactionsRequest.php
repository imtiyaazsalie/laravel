<?php

namespace App\Http\Requests\Paystack;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class PaystackSettlementTransactionsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->input('filter.tenant_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.settlement_id' => 'required|string',
            'filter.start_date' => 'nullable|date|date_format:Y-m-d',
            'filter.end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
        ];
    }
}
