<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DropInPackageTenantSettingsResource extends JsonResource
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
            'id' => $this->id,

            'name' => $this->name,

            'is_active' => $this->is_active,

            'tenant' => [
                'id' => $this->tenant->getKey(),
                'name' => $this->tenant->name,
                'dropInPackageSettings' => $this->when(Arr::exists($this->tenant->extra_parameters, 'dropInPackageSettings'), function () {
                    return $this->tenant->extra_parameters['dropInPackageSettings'];
                }),
            ],
        ];
    }
}
