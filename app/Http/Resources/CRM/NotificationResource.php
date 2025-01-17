<?php

namespace App\Http\Resources\CRM;

use App\Helpers\JsonResource;
use App\Http\Resources\TenantResource;
use Illuminate\Http\Request;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'description' => $this->description,
            'subject' => $this->subject,
            'status' => $this->status->toArray(),
            'content' => $this->content,
            'cc' => $this->cc,
            'unsubscribable' => $this->unsubscribable,

            'is_unsubscribed' => $this->when(isset($this->is_unsubscribed), $this->is_unsubscribed),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
        ];
    }
}
