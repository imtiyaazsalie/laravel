<?php

namespace App\Http\Requests\Region;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CreateRegionRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'country_code_iso2' => 'required|string:min:2|max:2',

            'currencies' => 'required|array',
            'currencies.*' => 'exists:currencies,currency_id',

            'timezones' => 'required|array',
            'timezones.*' => 'exists:timezones,timezone_id',
        ];
    }
}