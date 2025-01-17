<?php

namespace App\Http\Requests\UserPackage;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserPackageRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            permission: 'member_manage_package',
            tenantId: $this->route('userPackage')->package->tenant_id,
            userId: $this->route('userPackage')->user_id,
            userTypes: UserType::locationAdmins()
        );
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('userPackage')->package->isLimited()) {
            $this->merge([
                'sessions_available' => 'required|integer',
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'starting_on' => 'required|date',
            'ending_on' => 'nullable|date|after:starting_on',
        ];
    }
}
