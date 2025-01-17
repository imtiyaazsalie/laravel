<?php

namespace App\Http\Requests\Tags;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListTagTypesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
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
