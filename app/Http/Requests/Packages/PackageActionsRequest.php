<?php

namespace App\Http\Requests\Packages;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackageActionsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::tenantAdmins(),
            tenantId: $this->input('tenant_id'),
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
            'package_ids' => ['required', 'array'],
            'package_ids.*' => [
                'required',
                'integer',
                Rule::exists('packages', 'package_id')
                    ->where('is_active', true)
                    ->where('box_id', $this->input('tenant_id')),
            ],
            'late_cancellation_fee' => 'sometimes|nullable|numeric|min:0',
            'no_show_fee' => 'sometimes|nullable|numeric|min:0',
        ];
    }
}
