<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\Setting **/
class TenantSettingCoronavirusResource extends JsonResource
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

            'is_enabled' => $this->coronavirus_is_enabled,
            'is_enabled_questionnaire_member_app' => $this->coronavirus_is_enabled_questionnaire_member_app,
            'is_display_vaccination_details_roster' => $this->coronavirus_can_display_vaccination_details_roster,
            'is_display_vaccination_details_class' => $this->coronavirus_can_display_vaccination_details_class,
        ];
    }
}
