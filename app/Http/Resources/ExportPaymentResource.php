<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\LeadMember;
use App\Models\LocationUser;
use Illuminate\Support\Carbon;

/** @mixin \App\Models\UserInvoicePayment **/
class ExportPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $member = null;

        if (is_null($this->lead_member_id)) {
            $leadMember = LeadMember::find($this->lead_member_id);
            $member = $leadMember->first_name.' '.$leadMember->last_name;
        }

        if (is_null($this->lead_member_id)) {
            $userLocation = LocationUser::find($this->user_location_id);
            $member = $userLocation->user->name.' '.$userLocation->user->surname;
        }

        return [
            'invoice' => $this->code,
            'member' => $member,
            'date' => Carbon::parse($this->date_time)->format('Y-m-d'),
            'type' => $this->type,
            'amount' => number_format($this->amount, 2, '.', ''),
        ];
    }
}
