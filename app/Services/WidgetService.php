<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Location;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserInvoice;
use App\Models\UserPackage;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Log;

class WidgetService
{
    public function sendPendingUserEmail(User $user, Location $location, int $invoiceId, ?int $locationPaymentGatewayId = null): void
    {
        $paymentLink = config('octiv.web_app_url')."/payment/$invoiceId";

        if ($locationPaymentGatewayId) {
            $paymentLink .= "?gid=$locationPaymentGatewayId";
        }

        try {
            $content = Markdown::parse(view('emails.widgets.sign-up-pending', [
                'memberName' => $user->full_name,
                'locationName' => $location->name,
                'paymentLink' => $paymentLink,
            ])->render())->__toString();

            (new CrmService())->createScheduledEmail(
                content: $content,
                subject: $location->name.' account creation',
                to: $user->email,
                replyTo: 'noreply@octivfitness.com',
                tenant: $location->tenant,
                location: $location,
                queue: 'high'
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
        }
    }

    public function completeSignUp(int $userId, int $tenantId, bool $isSuccessful): void
    {
        $crm = resolve(CrmService::class);

        $tenantUser = TenantUser::query()->findTenantUserBy($tenantId, $userId)->first();

        if ($isSuccessful) {
            if ($tenantUser->status === UserStatus::PENDING) {

                $tenantUser->update([
                    'status' => UserStatus::ACTIVE->value,
                    'activated_on' => now(),
                ]);

                $crm->scheduleWelcomeMessageMailer($tenantUser->user, $tenantUser->tenant, true, true);
            }
        } else {
            $crm->createScheduledEmail(
                content: Markdown::parse(view('emails.widgets.sign-up-failed'))->__toString(),
                subject: 'Octiv Payment Declined',
                to: $tenantUser->user->email,
            );

            //TODO: removeSignUpUser
            // $this->removeSignUpUser($user);
        }
    }

    public function completeDropIn(UserInvoice $invoice, bool $isSuccessful): void
    {
        $leadUserPackage = $invoice->userPackage;

        if (! $leadUserPackage instanceof UserPackage) {
            return;
        }

        if ($isSuccessful) {
            $leadUserPackage->update(['sessions_available' => $leadUserPackage->package->limit]);

            $this->sendDropInSuccessNotifications($invoice);
        } else {
            (new CrmService())->createScheduledEmail(
                content: Markdown::parse(view('emails.widgets.drop-in-failed')->render())->__toString(),
                subject: 'Octiv Payment Declined',
                to: $invoice->userLocation->leadMember->email_address,
            );
        }
    }

    public function sendDropInSuccessNotifications(UserInvoice $invoice): void
    {
        $leadMember = $invoice->userLocation->leadMember;

        if (! $leadMember) {
            return;
        }

        $crmService = new CrmService();
        $location = $leadMember->location;
        $tenant = $location->tenant;

        // Set sent on date and confirm that notification has been sent.
        $invoice->update(['sent_on' => now()]);

        // Notification to member
        $bookingLink = '<a target="blank" href="'.config('octiv.web_app_url').'/widget/schedule?publicToken='.$tenant->public_token.'&leadToken='.$leadMember->getKey().'">Book here</a>';

        // Send lead link
        $crmService->createScheduledEmailForNotification(
            tenantOrLocation: $location,
            context: 'drop_in_confirmation_member',
            recipient: $leadMember->user,
            data: [
                'member_name' => $leadMember->user->name,
                'member_surname' => $leadMember->user->surname,
                'location_name' => $location->name,
                'sessions_purchased' => $invoice->userPackage->package->limit,
                'booking_link' => $bookingLink,
            ],
            queue: 'high'
        );

        $coachesCcArray = $crmService->getCoachesForNotificationCcArray($leadMember->location);

        if (count($coachesCcArray) > 0) {
            $firstCoach = $coachesCcArray[0];
            unset($coachesCcArray[0]);

            // Notification to coach
            $crmService->createScheduledEmailForNotification(
                tenantOrLocation: $location,
                context: 'drop_in_confirmation_coach',
                recipient: $firstCoach,
                cc: $coachesCcArray,
                data: [
                    'member_name' => $leadMember->user->name,
                    'member_surname' => $leadMember->user->surname,
                    'member_email' => $leadMember->user->email,
                    'member_dob' => $leadMember->user->date_of_birth != '' ? $leadMember->user->date_of_birth->toDateString() : 'n/a',
                    'member_mobile' => $leadMember->user->mobile != '' ? $leadMember->user->mobile : 'n/a',
                    'member_notes' => $invoice->userPackage->notes != '' ? $invoice->userPackage->notes : 'n/a',
                    'location_name' => $location->name,
                    'sessions_purchased' => $invoice->userPackage->package->limit,
                ],
                queue: 'high',
            );
        }
    }
}
