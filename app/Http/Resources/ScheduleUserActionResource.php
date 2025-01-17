<?php

namespace App\Http\Resources;

use App\Enums\ScheduleUserAction;
use App\Helpers\JsonResource;
use App\Models\ScheduleUserAction as ScheduleUserActionModel;
use Illuminate\Http\Request;

/** @mixin ScheduleUserActionModel * */
class ScheduleUserActionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'status' => $this->status->toArray(),
            'action' => $this->action,
            'date' => $this->date->toDateString(),
            'failed_attempts' => $this->failed_attempts,

            $this->mergeWhen($this->action === ScheduleUserAction::PLACE_ON_HOLD, [
                'details' => [
                    'on_hold_note' => $this->on_hold_note,
                    'on_hold_pro_rata_fee' => $this->on_hold_pro_rata_fee,
                    'on_hold_release_date' => $this->on_hold_release_date?->toDateString(),
                    'extend_package_end_date' => $this->extend_package_end_date,
                ],
            ]),

            $this->mergeWhen($this->action === ScheduleUserAction::DEACTIVATE, [
                'details' => [
                    'last_debit_date' => $this->last_debit_date?->toDateString(),
                    'exclude_from_future_batches' => $this->exclude_from_future_batches,
                ],
            ]),

            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'user_tenant_id' => $this->userTenant->getKey(),
            'user_tenant' => new TenantUserResource($this->whenLoaded('userTenant')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on->toDateTimeString(),
        ];
    }
}
