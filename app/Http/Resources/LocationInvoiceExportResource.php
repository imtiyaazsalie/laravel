<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use JsonSerializable;

/** @mixin \App\Models\LocationInvoice **/
class LocationInvoiceExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        return [
            'code' => $this->code,
            'location' => $this->location->name,
            'description' => $this->description,
            'status' => $this->status,
            'due_at' => $this->due_on,
            'sent_at' => $this->sent_on,
            'outstanding' => $this->outstanding_amount,
            'amount' => $this->amount,
            'amount_in_rands' => $this->amount_in_rands,
        ];
    }
}
