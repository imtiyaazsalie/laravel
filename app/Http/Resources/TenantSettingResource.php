<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\Setting **/
class TenantSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),

            'theme' => $this->theme,
            'hidden_features' => $this->hidden_features,
            'logo' => $this->logo_url,

            'is_user_contract_visible_in_app' => $this->is_user_contract_visible_in_app,
            'locale_language' => $this->locale_language,

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'updated_by_id' => $this->updated_by_id,
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),

            'created_at' => $this->created_on?->toDateTimeString(),
            'updated_at' => $this->updated_on?->toDateTimeString(),
        ];
    }
}
