<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\RemoteConfig **/
class RemoteConfigResource extends JsonResource
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

            'maintenance_title' => $this->maintenance_title,
            'maintenance_body' => $this->maintenance_body,
            'version_web_app' => $this->version_web_app,
            'version_web_app_required' => $this->version_web_app_required,
            'version_app_store_ios' => $this->version_app_store_ios,
            'version_app_store_ios_required' => $this->version_app_store_ios_required,
            'version_app_store_android' => $this->version_app_store_android,
            'version_app_store_android_required' => $this->version_app_store_android_required,

            'updated_at' => $this->updated_on->toDateTimeString(),
        ];
    }
}
