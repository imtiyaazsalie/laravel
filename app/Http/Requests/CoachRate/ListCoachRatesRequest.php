<?php

namespace App\Http\Requests\CoachRate;

use App\Rules\PerPageRule;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListCoachRatesRequest extends FormRequest
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
        return [
            'filter.user_id' => 'required|integer|exists:users,user_id',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
