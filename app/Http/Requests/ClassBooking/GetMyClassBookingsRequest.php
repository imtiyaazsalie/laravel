<?php

namespace App\Http\Requests\ClassBooking;

use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class GetMyClassBookingsRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter.location_id' => 'nullable|integer|exists:box_facility,box_facility_id',
            'filter.start_date' => 'nullable|date|before_or_equal:today',
            'filter.end_date' => 'nullable|date|after_or_equal:start_date',
            'order' => 'nullable|string|in:ASC,DESC',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
