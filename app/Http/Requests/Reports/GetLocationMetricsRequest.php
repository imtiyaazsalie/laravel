<?php

namespace App\Http\Requests\Reports;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class GetLocationMetricsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.year' => 'required|integer',
            'filter.tenant_id' => 'nullable|integer|exists:boxes,box_id',
            'filter.region_id' => 'nullable|integer|exists:regions,region_id',
            'filter.location_category_id' => 'nullable|integer|exists:box_facility_categories,box_facility_category_id',
            'filter.is_monthly_breakdown' => [new BooleanRule()],
        ];
    }
}
