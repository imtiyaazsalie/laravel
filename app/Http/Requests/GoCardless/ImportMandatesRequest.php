<?php

namespace App\Http\Requests\GoCardless;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class ImportMandatesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
