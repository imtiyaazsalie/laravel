<?php

namespace App\Http\Requests\OnHoldUser;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOnHoldUserRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('userOnHold')->tenant_id,
            userId: $this->route('userOnHold')->user_id,
            userIdStatuses: [
                UserStatus::ON_HOLD,
            ],
            userTypes: UserType::locationAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'release_date' => 'nullable|date|after:start_date',
            'pro_rata_fee' => ['nullable', new PriceRule()],
            'note' => 'nullable|string',
            'is_extend_package_end_date' => ['nullable', new BooleanRule()],
        ];
    }
}
