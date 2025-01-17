<?php

namespace App\Http\Requests\Leaderboard;

use App\Enums\Gender;
use App\Enums\UserType;
use App\Models\Exercise;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListLeaderboardRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: $this->input('filter.tenant_id'),
            locationId: $this->input('filter.location_id'),
            allowMember: true,
            userId: auth()->user()->getAuthIdentifier(),
        );

        // will remove once confirmed

        if ($exercisedId = $this->input('filter.exercise_id')) {
            $exercise = Exercise::findOrFail($exercisedId);

            if ($exercise->isGlobal()) {
                return true;
            }

            return $this->canOperate(
                userTypes: UserType::tenantUsers(),
                tenantId: $exercise->tenant_id,
                allowMember: true
            );
        }

        if ($tenantId = $this->input('filter.tenant_id')) {
            return $this->canOperate(
                userTypes: UserType::tenantUsers(),
                tenantId: $tenantId,
                locationId: $this->input('filter.location_id'),
                allowMember: true
            );
        }

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            allowMember: true,
            userId: auth()->user()->getAuthIdentifier()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => ['nullable', 'integer', 'exists:boxes,box_id'],
            'filter.location_id' => 'nullable|integer|exists:box_facility,box_facility_id',
            'filter.exercise_id' => ['nullable', 'integer', 'exists:exercise,exercise_id'],
            'filter.region_id' => ['nullable', 'integer', 'exists:regions,region_id'],
            'filter.gender_id' => ['nullable', new Enum(Gender::class)],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
