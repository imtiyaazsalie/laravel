<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\ClassDay;
use Illuminate\Http\Request;

/** @mixin ClassDay **/
class ClassDayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
        ];
    }
}
