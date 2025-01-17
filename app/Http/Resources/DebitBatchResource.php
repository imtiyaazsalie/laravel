<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\DebitBatch;
use Illuminate\Http\Request;

/** @mixin DebitBatch * */
class DebitBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),
            'debit_day_date_id' => $this->debit_day_date_id,
            'debit_day_date' => new DebitDayDateResource($this->whenLoaded('debitDayDate')),
            'users_count' => $this->debit_batch_num_users,
            'total' => $this->debit_batch_total,
            'subid' => $this->subid,
            'validation_status' => $this->validation_status,
            'report_token' => $this->report_token,
            'is_processed' => $this->is_processed,
            'processed_at' => $this->dt_processed?->toDateTimeString(),
            'filename' => $this->debit_batch_filename,
            'created_at' => $this->dt_added->toDateTimeString(),
            'updated_at' => $this->dt_modified->toDateTimeString(),
        ];
    }
}
