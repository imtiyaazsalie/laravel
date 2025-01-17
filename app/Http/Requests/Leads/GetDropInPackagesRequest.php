<?php

namespace App\Http\Requests\Leads;

use App\Traits\Authorize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetDropInPackagesRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [];

        return array_merge($rules, [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'lead_token' => 'required',
        ]);
    }
}
