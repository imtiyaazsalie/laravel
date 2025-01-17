<?php

namespace App\Http\Requests\TenantUser;

use App\Enums\UserDebitStatus;
use App\Enums\UserType;
use App\Rules\DebitDayRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateDebitStatusRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                ...UserType::locationAdmins(),
                UserType::SUPER_ADMINISTRATOR,
                UserType::ADMIN,
            ],
            tenantId: $this->tenant_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,user_id'],
            'debit_status_id' => ['required', new Enum(UserDebitStatus::class)],
            'debit_day_id' => ['required_if:debit_status_id,'.UserDebitStatus::DEBIT_ORDER->value, 'integer', new DebitDayRule],
        ];
    }
}
