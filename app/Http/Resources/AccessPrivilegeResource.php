<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\AccessPrivilege;
use Illuminate\Http\Request;

/** @mixin AccessPrivilege * */
class AccessPrivilegeResource extends JsonResource
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
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
        ];
    }
}
