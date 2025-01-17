<?php

namespace App\Http\Requests\Reports;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class GetMetricsForRegionsRequest extends FormRequest
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
            'region_id' => 'nullable|integer|exists:regions,region_id',
        ];
    }
}
