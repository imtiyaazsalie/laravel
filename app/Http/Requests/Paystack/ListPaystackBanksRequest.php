<?php

namespace App\Http\Requests\Paystack;

use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class ListPaystackBanksRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.country' => 'required',
        ];
    }
}
