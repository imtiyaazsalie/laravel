<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserTenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): Collection
    {
        if ($this->user) {
            $user = new UserResource($this->user);
        } else {
            $user = [];
        }

        return collect($user)->merge([
            'user_tenant' => new TenantUserResource($this->unsetRelation('user')),
        ]);
    }
}
