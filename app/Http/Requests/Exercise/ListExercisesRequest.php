<?php

namespace App\Http\Requests\Exercise;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ListExercisesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @throws ValidationException
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::allRoles(),
            tenantId: $this->input('filter.tenant_id'),
            allowMember: true
        );
    }

    protected function prepareForValidation(): void
    {
        if (auth()->user()->isAdmin()) {
            $data = $this->request->all();
            $data['filter']['type'] = 'global';
            $this->query->add($data);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'filter.type' => 'nullable|in:global,owned',
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.is_active' => new BooleanRule,
            'filter.is_benchmark' => new BooleanRule,
            'filter.exercise_category_id' => 'nullable|exists:exercise_category,exercise_category_id',
            'filter.search' => 'nullable|string|min:3|max:120',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];

        if (auth()->user()->isAdmin()) {
            $rules['filter.type'] = 'required|in:global';
        }

        if (($this->input('filter.type') == 'owned' && ! auth()->user()->isAdmin())) {
            $rules['filter.tenant_id'] = 'required|exists:boxes,box_id';
        }

        if ((empty($this->input('filter.type')) && ! auth()->user()->isAdmin())) {
            $rules['filter.tenant_id'] = 'required|exists:boxes,box_id';
        }

        return $rules;
    }
}
