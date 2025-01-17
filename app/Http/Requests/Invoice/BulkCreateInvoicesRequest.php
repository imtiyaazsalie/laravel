<?php

namespace App\Http\Requests\Invoice;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class BulkCreateInvoicesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            permission: 'accounts_invoice_actions',
            tenantId: Tenant::query()->findOrFail($this->input('tenant_id'))->getKey()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,user_id'],
            'due_on' => 'required|date',
            'is_send' => ['sometimes', new BooleanRule()],
        ];
    }
}
