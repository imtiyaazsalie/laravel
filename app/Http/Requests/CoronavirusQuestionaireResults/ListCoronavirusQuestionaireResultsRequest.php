<?php

namespace App\Http\Requests\CoronavirusQuestionaireResults;

use App\Enums\UserType;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListCoronavirusQuestionaireResultsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: Arr::get($this->filter, 'tenant_id'),
            locationId: Arr::get($this->filter, 'location_id'),
            allowMember: (bool) Arr::get($this->filter, 'user_id'),
            userId: Arr::get($this->filter, 'user_id'),
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.location_id' => 'nullable|integer|exists:box_facility,box_facility_id',
            'filter.user_id' => 'nullable|integer|exists:users,user_id',
            'filter.date' => ['nullable', 'date'],
            'filter.class_date_id' => ['nullable', 'integer', 'exists:class_to_dates,class_to_date_id'],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
