<?php

namespace App\Http\Requests\BodyWeight;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class StoreBodyWeightRequest extends FormRequest
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
            userId: $this->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,user_id',
            'weight' => [
                'required',
                'regex:/^\d{2,3}(\.\d{1,2})?$/',
            ],
            'recorded_at' => 'required|date|date_format:Y-m-d',
        ];
    }
}
