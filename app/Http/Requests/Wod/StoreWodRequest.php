<?php

namespace App\Http\Requests\Wod;

use App\Enums\UserType;
use App\Rules\UniqueWodDateRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class StoreWodRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            permission: 'wod_actions',
            tenantId: $this->tenant_id,
            userTypes: UserType::tenantStaff()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'integer|exists:boxes,box_id',
            'name' => 'nullable|string|min:3|max:80',
            'description' => 'nullable|string',
            'programme_id' => ['required', 'integer', 'exists:programmes,id'],
            'date' => ['required', 'date_format:Y-m-d', 'date', new UniqueWodDateRule],
            'warm_up' => 'nullable|string',
            'cool_down' => 'nullable|string',
            'coach_notes' => 'nullable|string',
            'member_notes' => 'nullable|string',
            'exercises' => 'required|array',
            'exercises.*' => 'required|array',
            'exercises.*.benchmark_id' => ['nullable', 'integer', 'exists:exercise,exercise_id'],
            'exercises.*.name' => 'string',
            'exercises.*.prefix' => 'string',
            'exercises.*.description' => 'string',
            'exercises.*.resource_url' => 'nullable|active_url',
            'exercises.*.measure_id' => ['integer', 'exists:measuring_units,measuring_unit_id'],
            'exercises.*.order' => 'required|integer',
        ];
    }
}
