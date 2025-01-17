<?php

namespace App\Http\Requests\LogBook;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListLogBookRequest extends FormRequest
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
            allowMember: true,
            userId: Arr::get($this->filter, 'user_id'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.user_id' => 'required|integer|exists:users,user_id',
            'filter.exercise_id' => ['nullable', 'integer', 'exists:exercise,exercise_id'],
            'filter.type' => 'nullable|in:benchmarkExercises,wodExercises,personalExercises',
            'filter.search' => ['nullable', 'string', 'regex:/^[\x20-\x7E]*$/'],
            'filter.is_only_return_best' => new BooleanRule,
            'per_page' => new PerPageRule(),
        ];
    }
}
