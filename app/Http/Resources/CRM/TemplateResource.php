<?php

namespace App\Http\Resources\CRM;

use App\Helpers\JsonResource;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;

class TemplateResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->template_id,
            'name' => $this->name,
            'content' => $this->content,
            'type' => $this->type,
            'deleted' => $this->deleted,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'created_at' => $this->createdOn,
            'updated_at' => $this->updatedOn,
        ];
    }
}
