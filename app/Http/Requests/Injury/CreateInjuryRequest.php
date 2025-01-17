<?php

namespace App\Http\Requests\Injury;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateInjuryRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $forSelf = auth()->user()->getAuthIdentifier() === (int) $this->user_id;

        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            tenantId: ! $forSelf ? $this->tenant_id : null,
            allowMember: true,
            userId: $this->user_id,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $forSelf = auth()->user()->getAuthIdentifier() === (int) $this->user_id;

        return [
            'tenant_id' => [$forSelf ? 'nullable' : 'required', 'integer', 'exists:boxes,box_id'],
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'content' => 'required|string',
        ];
    }
}
