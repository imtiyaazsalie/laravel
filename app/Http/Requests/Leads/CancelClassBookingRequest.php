<?php

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

class CancelClassBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_booking_id' => ['required', 'integer', 'exists:class_bookings,class_booking_id'],
            'lead_token' => ['required'],
        ];
    }
}
