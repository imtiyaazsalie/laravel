<?php

namespace App\Http\Requests\UserPackage;

use App\Enums\UserType;
use App\Models\UserPackage;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateUserPackageRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        /** @var UserPackage $userPackage */
        $userPackage = $this->route('userPackage');

        return $this->canOperate(
            tenantId: $userPackage->package->tenant_id,
            userId: $userPackage->user_id,
            userTypes: UserType::locationAdmins(),
            permissions: [
                'default' => ['member_manage_package'],
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
