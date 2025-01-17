<?php

namespace App\Http\Requests\UserBatch;

use App\Enums\UserType;
use App\Models\DebitBatch;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserBatchRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $debitBatch = DebitBatch::findOrFail($this->debit_batch_id);

        return $this->canOperate(
            tenantId: $debitBatch->location->tenant_id,
            locationId: $debitBatch->location_id,
            userId: $this->user_id,
            userTypes: UserType::locationAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'debit_batch_id' => ['required', 'integer', 'exists:debit_batches,debit_batch_id'],
        ];
    }
}
