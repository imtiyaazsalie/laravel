<?php

namespace App\Http\Requests\UserPackage;

use App\Enums\UserType;
use App\Models\ClassDate;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PackagesAvailableForClassRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $classDate = ClassDate::query()->findOrFail($this->input('class_date_id'));

        return $this->canOperate(
            userTypes: UserType::GYM_MEMBER,
            tenantId: $classDate->class->tenant_id,
            allowMember: true
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
            //
            'user_id' => 'required|integer|exists:users,user_id',
            'class_date_id' => 'required|integer|exists:class_to_dates,class_to_date_id',
        ];
    }
}
