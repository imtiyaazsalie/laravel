<?php

namespace App\Http\Requests\PushNotifications;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class DeletePushNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->route('pushNotification')->userLocation->user_id === auth()->user()->getAuthIdentifier();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
