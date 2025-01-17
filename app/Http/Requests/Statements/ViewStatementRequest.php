<?php

namespace App\Http\Requests\Statements;

use App\Enums\UserType;
use App\Rules\DatesBetweenRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ViewStatementRequest extends FormRequest
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
            'filter.user_id' => 'required|integer|exists:users,user_id',
            'filter.tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'filter.between' => ['required', new DatesBetweenRule()],
        ];
    }
}
