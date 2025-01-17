<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\UserBatch;
use Illuminate\Http\Request;

/** @mixin UserBatch **/
class UserBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'amount' => $this->amount_editable,
            'note' => $this->user_note,

            'is_active' => $this->is_active,
            'is_manually_added' => $this->manually_added,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'debit_batch_id' => $this->debit_batch_id,
            'debit_batch' => new DebitBatchResource($this->whenLoaded('debitBatch')),

            'invoice_id' => $this->invoice_id,
            'invoice' => new UserInvoiceResource($this->whenLoaded('invoice')),

            'created_at' => $this->dt_added->toDateTimeString(),
            'updated_at' => $this->dt_modified->toDateTimeString(),
        ];
    }
}
