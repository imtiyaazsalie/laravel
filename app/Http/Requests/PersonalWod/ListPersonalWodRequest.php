<?php

namespace App\Http\Requests\PersonalWod;

use App\Enums\UserType;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListPersonalWodRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: Arr::get($this->filter, 'tenant_id'),
            allowMember: true,
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
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.user_id' => 'nullable|integer|exists:users,user_id',
            'filter.search' => 'nullable|string',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
