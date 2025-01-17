<?php

namespace App\Services\CRM;

use App\Enums\UserType;
use App\Models\ClassBooking;
use App\Models\TenantUser;
use App\Services\ClassService;
use App\Services\CrmService;

class DiscoveryNotificationsService
{
    public function sendBookingConfirmationToMember(ClassBooking $classBooking): void
    {
        (new CrmService())->createScheduledEmailForNotification(
            tenantOrLocation: $classBooking->class->location,
            context: 'discovery_booking_confirmation_member',
            recipient: $classBooking->user->email,
            data: $this->buildDataArray($classBooking),
            queue: 'high',
        );

    }

    public function sendBookingConfirmationToCoach(ClassBooking $classBooking): void
    {
        $headCoaches = TenantUser::query()
            ->active()
            ->headCoaches()
            ->where('box_id', $classBooking->tenant_id)
            ->whereHas('user', fn ($query) => $query->where('deleted', 0))
            ->get();

        if ($headCoaches->isNotEmpty()) {
            $locationAdmins = TenantUser::query()
                ->active()
                ->where('user_type_id', UserType::BOX_FACILITY_ADMIN)
                ->where('box_id', $classBooking->tenant_id)
                ->whereHas('userLocation', function ($query) use ($classBooking) {
                    $query->active()
                        ->where('box_facility_id', $classBooking->class->location_id);
                })
                ->get()
                ->pluck('user.email')
                ->toArray();

            $primaryRecipient = $headCoaches->first()->user;

            $headCoachEmails = $headCoaches
                ->filter(fn ($headCoach) => $headCoach->user->user_id !== $primaryRecipient->user_id)
                ->pluck('user.email')
                ->toArray();

            $ccRecipients = array_merge($headCoachEmails, $locationAdmins);

            // Notification to coach
            (new CrmService())->createScheduledEmailForNotification(
                tenantOrLocation: $classBooking->class->location,
                context: 'discovery_booking_confirmation_coach',
                recipient: $primaryRecipient,
                cc: $ccRecipients,
                data: $this->buildDataArray($classBooking),
                queue: 'high',
            );
        }
    }

    public function buildDataArray(ClassBooking $classBooking): array
    {
        $start = (new ClassService())->getClassDateStartDateTime($classBooking->classDate);

        return [
            'member_name' => $classBooking->user->name,
            'member_surname' => str($classBooking->user->surname)->ucfirst()->charAt(0),
            'location_name' => $classBooking->class->location->name,
            'class_name' => $classBooking->classDate->name(),
            'class_time' => $start->format('H:i'),
            'class_date' => $start->toDateString(),
            'location_contact_details' => $classBooking->class->location->phone_number,
            'location_address' => $classBooking->class->location->address,
        ];
    }
}
