<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),

            'name' => $this->name,
            'description' => $this->description,

            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
