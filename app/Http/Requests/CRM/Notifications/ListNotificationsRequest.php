<?php

namespace App\Http\Requests\CRM\Notifications;

use App\Enums\UserType;
use App\Models\Tenant;
use App\Rules\PerPageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantAdmins(),
            tenantId: Tenant::query()->findOrFail($this->input('filter.tenant_id'))->getKey(),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'required|integer|exists:boxes,box_id',
            'page' => 'integer',
            'per_page' => new PerPageRule(),
        ];
    }
}
