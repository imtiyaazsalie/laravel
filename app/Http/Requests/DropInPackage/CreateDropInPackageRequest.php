<?php

namespace App\Http\Requests\DropInPackage;

use App\Enums\UserType;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateDropInPackageRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->tenant_id,
            userTypes: UserType::tenantAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|exists:boxes,box_id',
            'name' => 'required|string|min:3|max:254',
            'description' => 'required|string',
            'price' => new PriceRule(),
            'priority' => 'nullable|numeric',
        ];
    }
}
