<?php

namespace App\Http\Requests\StripeConnect;

use Illuminate\Foundation\Http\FormRequest;

class PostSetupIntentLinkRequest extends FormRequest
{
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
            'user_id' => 'required|exists:users,user_id',
            'location_id' => 'required|exists:box_facility,box_facility_id',
        ];
    }
}
