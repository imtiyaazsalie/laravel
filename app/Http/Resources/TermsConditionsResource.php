<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\TermsConditions **/
class TermsConditionsResource extends JsonResource
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
            'released_at' => $this->released_on?->toDateTimeString(),

            'created_at' => $this->created_on->toDateTimeString(),
        ];
    }
}
