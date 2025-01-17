<?php

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

class CreateClassBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_date_id' => ['required', 'integer', 'exists:class_to_dates,class_to_date_id'],
            'lead_token' => ['required'],
        ];
    }
}
