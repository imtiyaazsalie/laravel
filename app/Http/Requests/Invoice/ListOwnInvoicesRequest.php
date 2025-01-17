<?php

namespace App\Http\Requests\Invoice;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\DatesBetweenRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListOwnInvoicesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::GYM_MEMBER,
            tenantId: Tenant::query()->find($this->input('filter.tenant_id'))?->getKey(),
            allowMember: true,
            userId: $this->user()->getAuthIdentifier()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.between' => ['nullable', new DatesBetweenRule()],
            'filter.status' => 'nullable|string',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
