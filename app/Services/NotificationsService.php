<?php

namespace App\Services;

use App\Models\DropInPackageLeadMember;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;

class NotificationsService
{
    public function __construct(
        protected ?Tenant $tenant,
        protected ?Location $location,
        protected ?User $user,
        protected CrmService $crm
    ) {
        // code...
    }

    public function setBox(Tenant $tenant)
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function setFacility(Location $location)
    {
        $this->location = $location;

        return $this;
    }

    public function setUser(User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Send welcome mail to user with password reset link.
     *
     * It is required to set boxa and facility before calling this method.
     *
     *
     * @return void
     */
    public function sendDropInBookingLink(DropInPackageLeadMember $dropInPackageLeadMember)
    {
        $bookingLink = '<a target="blank" href="'.config('octiv.web_app_url').'/widget/schedule?publicToken='.$this->tenant->public_token.'&leadToken='.$dropInPackageLeadMember->token.'">Book here</a>';

        $this->crm->createScheduledEmailForNotification(
            tenantOrLocation: $this->location ?: $this->tenant,
            context: 'drop_in_confirmation_member',
            recipient: $dropInPackageLeadMember->leadMember,
            replyTo: $this->crm->getReplyTo($this->tenant),
            data: [
                'member_name' => $dropInPackageLeadMember->leadMember->first_name,
                'member_surname' => $dropInPackageLeadMember->leadMember->last_name,
                'location_name' => $this->location->location->name,
                'sessions_purchased' => $dropInPackageLeadMember->sesions_purchased,
                'booking_link' => $bookingLink,
            ]
        );
    }
}
