<?php

namespace App\Http\Requests\Leads;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\CSVRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportLeadMembersRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: Tenant::findOrFail($this->input('tenant_id'))->getKey()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'file' => ['required', new CSVRule],
        ];
    }
}
