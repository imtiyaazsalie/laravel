<?php

namespace App\Http\Requests\DropInPackage;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListDropInPackagesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: Arr::get($this->filter, 'tenant_id'),
            userTypes: UserType::tenantAdmins()
        );
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->get('status', 'active') === 'active' ? 1 : 0,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|exists:boxes,box_id',
            'filter.status' => ['required', new BooleanRule()],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
