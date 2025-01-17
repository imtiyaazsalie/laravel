<?php

namespace App\Http\Requests\Invoice;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoicesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: Tenant::query()->findOrFail($this->input('tenant_id'))->getKey(),
            userId: $this->input('user_id'),
            permissions: [
                'default' => ['accounts_invoice_actions'],
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:boxes,box_id'],
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'due_on' => 'required|date|after:today',
            'is_send' => ['required', new BooleanRule()],
        ];
    }
}
