<?php

namespace App\Http\Requests\OwnBenchmark;

use App\Enums\UserType;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListOwnBenchmarksRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
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
            'filter.user_id' => 'required|integer|exists:users,user_id',
            'filter.exercise_id' => ['nullable', 'integer', 'exists:exercise,exercise_id'],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
