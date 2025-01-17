<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use JsonSerializable;

/** @mixin \App\Models\InvoiceItemType **/
class FinanceInvoiceItemTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
        ];
    }
}
