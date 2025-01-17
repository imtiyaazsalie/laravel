<?php

namespace App\Http\Requests\Paystack;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class PaystackSettlementsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::locationAdmins(),
            tenantId: $this->route('location')->tenant_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.start_date' => 'nullable|date|date_format:Y-m-d',
            'filter.end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:filter.start_date',
        ];
    }
}
