<?php

namespace App\Http\Requests\BodyMeasurements;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class StoreBodyMeasurementRequest extends FormRequest
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
            'tricep' => [
                'sometimes',
                'regex:/^(?:[1-9]?\d(?:\.\d{1,3})?|1\d{2}(?:\.\d{1,3})?|200(?:\.0{1,3})?)$/',
            ],
            'subscapular' => [
                'sometimes',
                'regex:/^(?:[1-9]?\d(?:\.\d{1,3})?|1\d{2}(?:\.\d{1,3})?|200(?:\.0{1,3})?)$/',
            ],
            'abdominal' => [
                'sometimes',
                'regex:/^(?:[1-9]?\d(?:\.\d{1,3})?|1\d{2}(?:\.\d{1,3})?|200(?:\.0{1,3})?)$/',
            ],
            'suprailiac' => [
                'sometimes',
                'regex:/^(?:[1-9]?\d(?:\.\d{1,3})?|1\d{2}(?:\.\d{1,3})?|200(?:\.0{1,3})?)$/',
            ],
            'thigh' => [
                'sometimes',
                'regex:/^(?:[1-9]?\d(?:\.\d{1,3})?|1\d{2}(?:\.\d{1,3})?|200(?:\.0{1,3})?)$/',
            ],
            'calf' => [
                'sometimes',
                'regex:/^(?:[1-9]?\d(?:\.\d{1,3})?|1\d{2}(?:\.\d{1,3})?|200(?:\.0{1,3})?)$/',
            ],
            'body_fat_percentage' => [
                'sometimes',
                'regex:/^(?:[1-9]?\d(?:\.\d{1,3})?|1\d{2}(?:\.\d{1,3})?|200(?:\.0{1,3})?)$/',
            ],
            'recorded_at' => 'required|date|date_format:Y-m-d',
        ];
    }
}
