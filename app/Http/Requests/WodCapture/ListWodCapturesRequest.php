<?php

namespace App\Http\Requests\WodCapture;

use App\Enums\UserType;
use App\Models\Wod;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ListWodCapturesRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        $wod = Wod::findOrFail($this->input('filter.wod_id'));

        return $this->canOperate(
            tenantId: $wod->tenant_id,
            allowMember: true,
            userId: Arr::get($this->filter, 'user_id'),
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter.wod_id' => ['required', 'integer', 'exists:wods,wod_id'],
            'filter.user_id' => 'integer|exists:users,user_id',
        ];
    }
}
