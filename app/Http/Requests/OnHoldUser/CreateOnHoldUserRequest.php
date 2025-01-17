<?php

namespace App\Http\Requests\OnHoldUser;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateOnHoldUserRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->tenant_id
        );

    }

    public function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'is_extend_package_end_date' => false,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'user_ids' => ['required', 'array', 'exists:users,user_id'],
            'start_date' => 'required|date',
            'release_date' => 'nullable|date|after:start_date',
            'pro_rata_fee' => ['nullable', new PriceRule()],
            'note' => 'nullable|string',
            'is_extend_package_end_date' => [new BooleanRule()],
        ];
    }
}
