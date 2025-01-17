<?php

namespace App\Http\Requests\Reports\Report;

use App\Enums\Gender;
use App\Enums\UserType;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class PerformanceRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
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
            'filter.exercise_id' => ['required', 'integer', 'exists:exercise,exercise_id'],
            'filter.user_id' => 'nullable|integer|exists:users,user_id',
            'filter.gender_id' => ['nullable', new Enum(Gender::class)],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
