<?php

namespace App\Http\Resources\CRM;

use App\Helpers\JsonResource;
use App\Http\Resources\TenantResource;
use App\Models\BroadcastMessages;
use Illuminate\Http\Request;

/** @mixin BroadcastMessages **/
class BroadcastMessageResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'message' => $this->message,
            'is_active' => $this->is_active,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'updated_at' => $this->dt_modified?->toDateTimeString(),
        ];
    }
}
