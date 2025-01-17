<?php

namespace App\Http\Requests\DropInPackage;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDropInPackageSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantAdmins(),
            permission: 'settings',
            tenantId: $this->tenant_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|exists:boxes,box_id',
            'show_all_classes' => 'required|in:yes,no',
            'payment_type' => ['required', 'in:cash,adhoc'],
        ];
    }
}
