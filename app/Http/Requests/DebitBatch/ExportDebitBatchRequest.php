<?php

namespace App\Http\Requests\DebitBatch;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportDebitBatchRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->route('debitBatch')->location->tenant_id,
            locationId: $this->route('debitBatch')->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'pain_format' => Rule::in('pain.008.001.02', 'pain.008.002.02', 'pain.008.003.02'),
            'is_process_as_batch' => ['nullable', new BooleanRule()],
        ];
    }
}
