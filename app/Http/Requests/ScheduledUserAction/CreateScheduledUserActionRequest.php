<?php

namespace App\Http\Requests\ScheduledUserAction;

use App\Enums\ScheduleUserAction;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateScheduledUserActionRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->tenant_id,
            userTypes: UserType::locationAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'action' => ['required', new Enum(ScheduleUserAction::class)],
            'user_ids' => ['required', 'array', 'exists:users,user_id'],
            'tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'action_date' => 'required|date|after:today',
            'pro_rata_fee' => ['nullable', new PriceRule()],
            'release_date' => 'nullable|date|after:today',
            'note' => 'nullable|string',
            'is_extend_package_end_date' => ['nullable', new BooleanRule()],
            'is_excluded_from_future_batches' => ['required', new BooleanRule()],
            'last_debit_date' => 'nullable|date',
        ];
    }
}
