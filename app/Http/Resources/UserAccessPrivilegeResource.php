<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\UserAccessPrivilege;
use Illuminate\Http\Request;

/** @mixin UserAccessPrivilege * */
class UserAccessPrivilegeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->getKey(),
            'revoked' => $this->revoked,

            'access_privilege_id' => $this->access_privilege_id,
            'access_privilege' => new AccessPrivilegeResource($this->whenLoaded('accessPrivilege')),

            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'created_on' => $this->created_on->toDateTimeString(),
            'updated_on' => $this->updated_on->toDateTimeString(),
            'revoked_on' => $this->revoked_on?->toDateTimeString(),
        ];
    }
}
