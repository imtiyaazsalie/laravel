<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\Currency **/
class CurrencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),

            'name' => $this->name,
            'code' => $this->code,
            'notes' => $this->notes,
        ];
    }
}
