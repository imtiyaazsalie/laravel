<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\WodCaptureLikes **/
class WodCaptureLikeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),

            'wod_capture_id' => $this->wod_capture_id,
            'wod_capture' => new WodCaptureResource($this->whenLoaded('wodCapture')),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),
        ];
    }
}
