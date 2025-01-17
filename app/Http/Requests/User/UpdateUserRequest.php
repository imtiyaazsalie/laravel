<?php

namespace App\Http\Requests\User;

use App\Enums\Gender;
use App\Enums\UserType;
use App\Rules\IdNumberRule;
use App\Rules\ImageRule;
use App\Rules\MobileNumberRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: [
                ...UserType::tenantUsers(),
                UserType::SUPER_ADMINISTRATOR,
            ],
            allowMember: true,
            userId: $this->route('user')->getAuthIdentifier(),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id_number' => [
                'nullable',
                new IdNumberRule(),
            ],
            'address' => 'nullable|string',
            'health_provider_id' => [
                'nullable', '
                integer',
                'exists:health_providers,id',
            ],
            'name' => [
                'sometimes',
                'string',
                'min:1',
                'max:120',
            ],
            'surname' => [
                'sometimes',
                'string',
                'min:1',
                'max:120',
            ],
            'gender_id' => [
                'nullable', new Enum(Gender::class),
            ],
            'date_of_birth' => [
                'sometimes',
                'date',
                'before:today',
            ],
            'email' => [
                'sometimes',
                'email:rfc,dns',
                Rule::unique('users')
                    ->ignore($this->route('user')->getAuthIdentifier(), 'user_id')
                    ->where(
                        fn (Builder $query) => $query->whereNull('primary_user_account_id')
                            ->where('deleted', '=', 0)
                    ),

            ],
            'mobile' => ['sometimes', new MobileNumberRule()],
            'emergency_contact_name' => 'sometimes|string|min:1|max:120',
            'emergency_contact_mobile' => ['sometimes', new MobileNumberRule()],
            'image' => ['nullable', new ImageRule()],
            'password' => ['sometimes', Password::defaults()],
        ];
    }
}
