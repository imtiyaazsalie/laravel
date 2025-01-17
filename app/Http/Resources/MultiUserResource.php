<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

class MultiUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            $this->mergeWhen($this->non_member_name || $this->non_member_email, [
                'non_member' => [
                    'name' => $this->non_member_name,
                    'email' => $this->non_member_email,
                ],
            ]),

            'lead_member_id' => $this->lead_member_id,
            'lead_member' => new LeadMemberResource($this->whenLoaded('leadMember')),

            'tenant_user_id' => $this->tenant_user_id,
            'tenant_user' => new TenantUserResource($this->whenLoaded('tenantUser')),

            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
