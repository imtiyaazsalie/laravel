<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\PushNotification **/
class PushNotificationResource extends JsonResource
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
            'message' => $this->message,
            'is_read' => $this->is_read,
            'received_at' => $this->received_on?->toDateTimeString(),
        ];
    }
}
