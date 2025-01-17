<?php

namespace App\Http\Requests\ClassDate;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ReadClassDateRequest extends FormRequest
{
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
        return [];
    }
}
