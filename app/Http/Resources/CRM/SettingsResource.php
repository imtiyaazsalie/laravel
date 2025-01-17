<?php

namespace App\Http\Resources\CRM;

use App\Helpers\JsonResource;
use App\Http\Resources\LocationResource;
use Illuminate\Http\Request;

class SettingsResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'sms_status' => $this->sms_status,
            'email_status' => $this->email_status,
            'email_signature' => $this->email_signature,
            'reply_to' => $this->reply_to,
            'sender_name' => $this->sender_name,
            'file_absolute_path' => $this->file_absolute_path,

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),
        ];
    }
}
