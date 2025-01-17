<?php

namespace App\Http\Requests\Admin\BulkImport;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Rules\CSVRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class ImportUserBankingDetailsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
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
            'tenant_id' => 'required|exists:boxes,box_id',
            'csv_file' => ['required', new CSVRule],
            'is_upload' => new BooleanRule,
        ];
    }
}
