<?php

namespace App\Http\Requests\Programmes;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\UniqueProgrammeNameRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProgrammeRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('programme')->tenant_id,
            userTypes: UserType::locationAdmins()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255', new UniqueProgrammeNameRule(
                tenantId: $this->route('programme')->tenant_id,
                exclude: $this->route('programme')
            )],
            'description' => 'nullable|string',
            'is_active' => ['required', new BooleanRule()],
        ];
    }
}
