<?php

namespace App\Http\Requests\DebitBatch;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ResubmitDebitBatchRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: [
                ...UserType::locationAdmins(),
                UserType::SUPER_ADMINISTRATOR,
            ],
            permission: 'accounts_debit_batches',
            tenantId: $this->route('debitBatch')->location->tenant_id,
            locationId: $this->route('debitBatch')->location_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'date' => 'nullable|date|date_format:Y-m-d',
            'is_process_as_same_day_batch' => ['required', new BooleanRule()],
        ];
    }
}
