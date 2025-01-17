<?php

namespace App\Http\Requests\Injury;

use App\Enums\InjuryStatus;
use App\Enums\UserType;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Enum;

class ListInjuriesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: Arr::get($this->filter, 'tenant_id'),
            locationId: Arr::get($this->filter, 'location_id'),
            allowMember: true,
            userId: Arr::get($this->filter, 'user_id'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $forSelf = auth()->user()->getAuthIdentifier() == (int) $this->input('filter.user_id');

        return [
            'filter.tenant_id' => [! $forSelf ? 'required' : 'nullable', 'integer', 'exists:boxes,box_id'],
            'filter.location_id' => 'sometimes|integer|exists:box_facility,box_facility_id',
            'filter.user_id' => 'nullable|integer|exists:users,user_id',
            'filter.status' => ['nullable', 'string', new Enum(InjuryStatus::class)],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
