<?php

namespace App\Http\Requests\Admin\Exports;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ExportLocationsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR,
            tenantId: $this->input('filter.tenant_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter.tenant_id' => 'nullable|exists:boxes,box_id',
            'filter.region_id' => 'nullable|exists:regions,region_id',
            'filter.start_date' => 'required|date',
            'filter.end_date' => 'required|date|after_or_equal:filter.start_date',
        ];
    }
}
