<?php

namespace App\Http\Requests\GoCardless;

use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class MandateCallbackRequest extends FormRequest
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
            'redirect_flow_id' => 'required|string',
        ];
    }
}
