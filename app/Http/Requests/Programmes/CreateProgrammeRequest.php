<?php

namespace App\Http\Requests\Programmes;

use App\Enums\UserType;
use App\Rules\UniqueProgrammeNameRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateProgrammeRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->tenant_id,
            userTypes: UserType::locationAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'name' => ['required', 'string', 'min:3', 'max:255', new UniqueProgrammeNameRule($this->tenant_id)],
            'description' => 'nullable|string',
        ];
    }
}
