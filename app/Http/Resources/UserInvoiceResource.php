<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\UserInvoice;
use Illuminate\Http\Request;

/** @mixin UserInvoice * */
class UserInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status,
            'type' => $this->type,
            'amount' => $this->amount,
            'note' => $this->note,

            'discriminator' => $this->discriminator?->value,

            'due_on' => $this->due_on?->toDateString(),
            'sent_at' => $this->sent_on?->toDateTimeString(),
            'period_starts_on' => $this->period_start?->toDateString(),
            'period_ends_on' => $this->period_end?->toDateString(),

            $this->mergeWhen(($this->non_member_name !== null || $this->non_member_email !== null) || $this->lead_member_id !== null, new NonMemberResource($this)),

            'outstanding_amount' => $this->whenAppended('outstanding_amount'),
            'amount_in_cents' => $this->whenAppended('amount_in_cents'),

            $this->mergeWhen($this->nonce, ['nonce' => $this->nonce]),
            $this->mergeWhen($this->payment_gateway_settings, ['payment_gateway_settings' => $this->payment_gateway_settings]),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_location_id' => $this->user_to_facility_id,
            'user_location' => new UserLocationResource($this->whenLoaded('userLocation')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'lead_member_id' => $this->userLocation?->lead_member_id,

            $this->mergeWhen(! is_null($this->userLocation?->lead_member_id), [
                'lead_member' => $this->userLocation ? new LeadMemberResource($this->userLocation->leadMember) : null,
            ]),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'items' => UserInvoiceItemResource::collection($this->whenLoaded('invoiceItems')),
            'payments' => UserInvoicePaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => $this->created_on?->toDateTimeString(),

            $this->mergeWhen(! is_null($this->drop_in), [
                'drop_in' => $this->drop_in,
            ]),
        ];
    }
}
