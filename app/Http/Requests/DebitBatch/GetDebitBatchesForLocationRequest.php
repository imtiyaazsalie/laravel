<?php

namespace App\Http\Requests\DebitBatch;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class GetDebitBatchesForLocationRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: Arr::get($this->filter, 'tenant_id'),
            locationId: Arr::get($this->filter, 'location_id')
        );
    }

    public function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'is_return_all' => false,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'filter.location_id' => 'required|integer|exists:box_facility,box_facility_id',
            'is_return_all' => ['required', new BooleanRule()],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
