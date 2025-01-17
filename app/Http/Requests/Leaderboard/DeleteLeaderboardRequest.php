<?php

namespace App\Http\Requests\Leaderboard;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class DeleteLeaderboardRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            tenantId: $this->route('leaderboard')->exercise->tenant_id,
            userId: $this->route('leaderboard')->user_id,
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
