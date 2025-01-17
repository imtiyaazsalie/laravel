<?php

namespace App\Http\Requests\Programmes;

use App\Enums\Affiliate;
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
    public function authorize(): Response|bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.search' => 'nullable|string',
            'filter.is_active' => ['nullable', new BooleanRule()],
            'filter.is_global' => ['nullable', new BooleanRule()],
            'filter.affiliate_id' => ['nullable', new EnumRule(Affiliate::class)],
            'filter.package_id' => ['nullable', 'exists:packages,package_id'],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
