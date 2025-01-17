<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\HealthCareProvider;
use Illuminate\Http\Request;

/** @mixin HealthCareProvider **/
class HealthProviderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'is_active' => $this->is_active,
        ];
    }
}
