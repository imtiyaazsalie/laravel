<?php

namespace App\Http\Requests\Region;

use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class ListRegionsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.is_active' => ['required', new BooleanRule],
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
