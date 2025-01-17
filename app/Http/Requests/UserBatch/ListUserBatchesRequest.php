<?php

namespace App\Http\Requests\UserBatch;

use App\Enums\UserType;
use App\Models\DebitBatch;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListUserBatchesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $debitBatch = DebitBatch::findOrFail($this->input('filter.debit_batch_id'));

        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $debitBatch->location->tenant_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.debit_batch_id' => 'required|integer|exists:debit_batches,debit_batch_id',
            'filter.search' => 'nullable|string|min:3|max:120',
            'filter.is_active' => ['nullable', new BooleanRule()],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
