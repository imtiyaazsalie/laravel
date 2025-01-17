<?php

namespace App\Http\Requests\AccessPrivileges;

use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class AccessPrivilegesListRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.user_type_id' => 'required|exists:user_types,user_type_id',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
