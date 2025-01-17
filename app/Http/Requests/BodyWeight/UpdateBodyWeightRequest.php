<?php

namespace App\Http\Requests\BodyWeight;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBodyWeightRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantUsers(),
            allowMember: true,
            userId: $this->route('bodyWeight')->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'weight' => [
                'required',
                'regex:/^\d{2,3}(\.\d{1,2})?$/',
            ],
            'recorded_at' => 'sometimes|date|date_format:Y-m-d',
        ];
    }
}
