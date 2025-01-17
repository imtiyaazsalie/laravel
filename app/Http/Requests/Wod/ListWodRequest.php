<?php

namespace App\Http\Requests\Wod;

use App\Rules\BooleanRule;
use App\Rules\PerPageRule;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListWodRequest extends FormRequest
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
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'filter.starts_after' => 'required|date_format:Y-m-d',
            'filter.ends_before' => 'required|date_format:Y-m-d|after_or_equal:filter.starts_after',
            'filter.use_workout_threshold' => [new BooleanRule()],
            'filter.programme_id' => ['nullable', 'integer', 'exists:programmes,id'],
            'per_page' => new PerPageRule,
        ];
    }
}
