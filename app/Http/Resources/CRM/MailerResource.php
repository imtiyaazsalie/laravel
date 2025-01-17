<?php

namespace App\Http\Resources\CRM;

use App\Http\Resources\LocationResource;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Mailer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Mailer * */
class MailerResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'name' => $this->name,
            'description' => $this->description,

            'title' => $this->title,
            'subject' => $this->subject,
            'content' => $this->content,

            'image_header' => $this->image_header_url,
            'image_footer' => $this->image_footer_url,

            'type' => $this->type,
            'status' => $this->status,

            'frequency' => $this->frequency,
            'next_scheduled_for' => $this->next_scheduled_for?->setTimezone($this->getTimezone()->zone)->toDateTimeString(),

            'reply_to' => $this->reply_to,
            'sender_name' => $this->sender_name,

            'sent_on' => $this->sent_on?->setTimezone($this->getTimezone()->zone)->toDateTimeString(),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'attachments' => MailerAttachmentResource::collection($this->whenLoaded('attachments')),
            'recipients' => MailerRecipientResource::collection($this->whenLoaded('recipients')),

            'created_at' => $this->created_on?->toDateTimeString(),
            'updated_at' => $this->updated_an?->toDateTimeString(),

            'deleted' => $this->deleted,
        ];
    }
}
