<?php

namespace App\Http\Requests\Tags;

use App\Enums\UserType;
use App\Models\Location;
use App\Models\Tag;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        /** @var Tag $tag */
        $tag = $this->route('tag');

        if ($tag->isOwnedByGym()) {
            $tenantId = $tag->owner_model_id;
            $locationId = null;
        } else {
            $location = Location::findOrFail($tag->owner_model_id);
            $tenantId = $location->tenant_id;
            $locationId = $location->getKey();
        }

        return $this->canOperate(
            tenantId: $tenantId,
            locationId: $locationId,
            userTypes: UserType::tenantUsers()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:120',
        ];
    }
}
