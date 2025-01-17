<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\Taggable;
use Illuminate\Http\Request;

/** @mixin Taggable **/
class TaggablesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'tag_id' => $this->tag_id,
            'tag' => new TagResource($this->whenLoaded('tag')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'updated_by_id' => $this->updated_by_id,
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),

            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
