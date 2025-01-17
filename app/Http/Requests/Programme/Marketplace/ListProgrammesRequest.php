<?php

namespace App\Http\Requests\Programme\Marketplace;

use App\Enums\Affiliate;
use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\EnumRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListProgrammesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            tenantId: $this->input('filter.tenant_id'),
            userTypes: UserType::tenantAdmins(),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.affiliate_id' => ['nullable', new EnumRule(Affiliate::class)],
            'filter.search' => 'nullable|string',
            'filter.is_active' => ['nullable', new BooleanRule()],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
