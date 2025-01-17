<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\WodCaptureComments **/
class WodCaptureCommentResource extends JsonResource
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
            'content' => $this->content,

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('user')),

            'wod_capture_id' => $this->wod_capture_id,
            'wod_capture' => new WodCaptureResource($this->whenLoaded('wod_capture')),

            'created_at' => $this->created_on->toDateTimeString(),
        ];
    }
}
