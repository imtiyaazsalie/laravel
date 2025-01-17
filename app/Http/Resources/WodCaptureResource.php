<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\WodCapture **/
class WodCaptureResource extends JsonResource
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

            'is_verified' => $this->is_verified,
            'verified_at' => $this->dt_verified?->toDateString(),

            'wod_id' => $this->wod_id,
            'wod' => new WodResource($this->whenLoaded('wod')),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'verified_by_id' => $this->verified_by_id,
            'verified_by' => new UserResource($this->whenLoaded('verifiedBy')),

            'likes' => WodCaptureLikeResource::collection($this->whenLoaded('likes')),
            'comments' => WodCaptureCommentResource::collection($this->whenLoaded('comments')),
            'exercises' => WodCaptureExerciseResource::collection($this->whenLoaded('exercises')),
        ];
    }
}
