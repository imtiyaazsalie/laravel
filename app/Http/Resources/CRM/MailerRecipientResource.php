<?php

namespace App\Http\Resources\CRM;

use App\Models\MailerRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MailerRecipient * */
class MailerRecipientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->getMailToName(),
            'email' => $this->getMailToAddress(),
            'mobile' => $this->getMobileNumber(),
            'ref_entity_id' => $this->ref_entity_id,
            'ref_entity_name' => $this->ref_entity_name,
            'type' => $this->type,
        ];
    }
}
