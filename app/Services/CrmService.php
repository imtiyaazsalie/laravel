<?php

namespace App\Services;

use App\Enums\MailerType;
use App\Enums\NotificationLogStatus;
use App\Enums\NotificationLogType;
use App\Enums\NotificationStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Jobs\RemoveMailAttachments;
use App\Jobs\SendDispatchableMail;
use App\Jobs\SendPushNotifications;
use App\Jobs\SendSms;
use App\Mail\CrmMail;
use App\Models\CrmMailingList;
use App\Models\CrmSetting;
use App\Models\Location;
use App\Models\LocationUser;
use App\Models\Mailer;
use App\Models\MailerRecipient;
use App\Models\NotificationLog;
use App\Models\Notifications;
use App\Models\PushNotification;
use App\Models\ScheduledEmail;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserInvoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class CrmService
{
    /**
     * Get tenant CRM settings
     */
    public function crm(string|int $boxId, string|int|null $locationId = null): CrmSetting
    {
        return CrmSetting::for($boxId, $locationId);
    }

    /**
     * Get tenant or default notification.
     */
    public function notification(string|int $boxId, string $context): Notifications
    {
        return Notifications::firstFor($boxId, $context);
    }

    /**
     * Get tenant or default notifications.
     */
    public function notifications(string|int $boxId, array $contexts): Collection
    {
        return Notifications::for($boxId, $contexts);
    }

    public function getReplyTo($tenantOrLocation)
    {
        $location = null;

        $headCoaches = [];

        if ($tenantOrLocation) {
            if ($tenantOrLocation instanceof Tenant) {
                $tenant = $tenantOrLocation;
            } else {
                $tenant = $tenantOrLocation->tenant;
                $location = $tenantOrLocation;
            }

            $headCoaches = User::query()
                ->join('user_to_box', 'user_to_box.user_id', '=', 'users.user_id')
                ->where('box_id', $tenant->getKey())
                ->where('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED)
                ->where('user_to_box.user_type_id', UserType::HEAD_COACH)
                ->where('user_to_box.end_date', '>', now())
                ->orderBy('users.name')
                ->get();
        }

        // REPLY-TO -  best case, use facility setting
        if ($location && $location->crmSettings && $location->crmSettings->reply_to) {
            $replyTo = $location->crmSettings->reply_to;
        } elseif ($headCoaches->isNotEmpty()) {
            // REPLY-TO -  third-case scenario, use first head coach email
            $replyTo = $headCoaches->first()->email;
        }

        return $replyTo ?? config('octiv.emails.noreply');
    }

    public function getCoachesForNotificationCcArray(Location $location): array
    {
        $coachEmails = [];
        $headCoachesAndBoxAdmins = (new TenantUserService())->getTenantUsersBy($location->tenant, [UserType::HEAD_COACH->value, UserType::BOX_ADMIN->value], null, [UserStatus::ACTIVE]);
        $locationAdmins = (new TenantUserService())->getTenantUsersBy($location->tenant, [UserType::BOX_FACILITY_ADMIN->value], $location, [UserStatus::ACTIVE]);

        if ($locationAdmins->isNotEmpty()) {
            foreach ($locationAdmins as $locationAdmin) {
                $coachEmails[] = $locationAdmin->user->email;
            }
        } elseif ($headCoachesAndBoxAdmins->isNotEmpty()) {
            foreach ($headCoachesAndBoxAdmins as $headCoachesAndBoxAdmin) {
                $coachEmails[] = $headCoachesAndBoxAdmin->user->email;
            }
        }

        return $coachEmails;
    }

    public function getSystemNotificationContent(Tenant|Location $tenantOrLocation, string $context, ?array $data = []): string
    {
        if ($tenantOrLocation instanceof Location) {
            $tenant = $tenantOrLocation->tenant;
        } else {
            $tenant = $tenantOrLocation;
        }

        $notification = Notifications::query()
            ->whereNull('box_id')
            ->where('system_context', '=', $context)
            ->first();

        // try to get a customised notification for this box
        $custom = Notifications::query()
            ->whereNotNull('system_context')
            ->whereNotNull('parent_id')
            ->where('status', '!=', 'deleted')
            ->where('box_id', $tenant->getKey())
            ->where('system_context', '=', $context)
            ->orderBy('name')
            ->first();

        if (! $custom) {
            $custom = $notification->replicate();

            $custom->fill([
                'parent_id' => $notification->getKey(),
                'box_id' => $tenant->getKey(),
                'type' => 'copy',
            ]);

            $custom->save();
        }

        return $custom->generateHtml($data);
    }

    public function getSystemNotificationSubject(Tenant|Location $tenantOrLocation, string $context, ?array $data = []): string
    {
        if ($tenantOrLocation instanceof Location) {
            $tenant = $tenantOrLocation->tenant;
        } else {
            $tenant = $tenantOrLocation;
        }

        $notification = Notifications::query()->where('system_context', '=', $context)->first();

        // try to get a customised notification for this box
        $custom = Notifications::query()
            ->whereNotNull('system_context')
            ->whereNotNull('parent_id')
            ->where('status', '!=', 'deleted')
            ->where('box_id', $tenant->getKey())
            ->where('system_context', '=', $context)
            ->orderBy('name')
            ->first();

        if (! $custom) {
            $custom = $notification->replicate();

            $custom->fill([
                'parent_id' => $notification->getKey(),
                'box_id' => $tenant->getKey(),
                'type' => 'copy',
            ]);

            $custom->save();
        }

        return $custom->getSubject($data);
    }

    /**
     * Send or schedule an SMS.
     */
    public function createScheduledSms(
        string $content,
        ?string $to = null,
        ?Tenant $tenant = null,
        ?Location $location = null,
        ?Mailer $mailer = null,
        string $queue = 'low',
    ): void {

        $user = User::query()->where('mobile', $to)->first();

        if (is_string($reason = $this->getPotentialErrorMessageForSms($location, $to))) {
            NotificationLog::create([
                'type' => NotificationLogType::SMS,
                'status' => NotificationLogStatus::CANCELLED,
                'recipient' => $to ?? 'null',
                'message' => $reason,

                'tenant_id' => $tenant?->getKey(),
                'location_id' => $location?->getKey(),
                'user_id' => $user?->getKey(),
                'mailer_id' => $mailer?->getKey(),

                'content' => $content,
            ]);

            return;
        }

        SendSms::dispatch(
            content: $content,
            to: $to,
            tenantId: $tenant?->getKey(),
            locationId: $location?->getKey(),
            mailerId: $mailer?->getKey(),
            userId: $user?->getKey(),
        )->onQueue($queue);
    }

    /**
     * Send or schedule a push notification.
     */
    public function createScheduledPushNotification(
        string $title,
        string $content,
        User $user,
        ?Tenant $tenant = null,
        ?Location $location = null,
        ?Mailer $mailer = null,
        string $queue = 'low',
    ): void {

        if (is_string($reason = $this->getPotentialErrorMessageForPushNotification($user))) {
            NotificationLog::create([
                'type' => NotificationLogType::PUSH,
                'status' => NotificationLogStatus::CANCELLED,
                'recipient' => $user->mobile ?? 'null',
                'message' => $reason,
                'tenant_id' => $tenant?->getKey(),
                'location_id' => $location?->getKey(),
                'user_id' => $user->getKey(),
                'mailer_id' => $mailer?->getKey(),
                'title' => $title,
                'content' => $content,
            ]);

            return;
        }

        $badgeCount = 1;

        if ($tenant || $location) {
            $locationUser = null;

            if ($location) {
                $locationUser = LocationUser::query()
                    ->where('user_id', $user->getAuthIdentifier())
                    ->where('box_facility_id', $location->getKey())
                    ->active()
                    ->first();
            } elseif ($tenant) {
                $locationUser = (new TenantUserService())->getLocationUserByTenant($user, $tenant);
            }

            if ($locationUser) {
                $badgeCount = PushNotification::nextBadgeCount($locationUser->getKey());

                PushNotification::create([
                    'user_location_id' => $locationUser->getKey(),
                    'title' => $title,
                    'message' => $content,
                    'received_on' => now(),
                ]);
            }
        }

        SendPushNotifications::dispatch(
            title: $title,
            content: $content,
            badge: $badgeCount,
            tokens: [$user->pushNotificationToken()]
        )->onQueue($queue);
    }

    /**
     * Send or schedule mail.
     */
    public function createScheduledEmail(
        string $content,
        string $subject,
        ?string $to = null,
        ?string $replyTo = null,
        ?array $attach = null,
        ?array $cc = null,
        ?string $signature = null,
        ?Tenant $tenant = null,
        ?Location $location = null,
        ?Notifications $notification = null,
        ?Mailer $mailer = null,
        string $queue = 'low',
        bool $skipUserChecks = false,
    ): bool|ScheduledEmail {

        $reason = null;

        if ($tenantOrLocation = $location ?: $tenant) {
            $signature = $this->getEmailSignatureContent($tenantOrLocation);
        }

        if ($mailer && $mailer->sender_name) {
            $fromName = $mailer->sender_name;
        } elseif ($location && $location->crmSettings && $location->crmSettings->sender_name) {
            $fromName = $location->crmSettings->sender_name;
        } elseif ($tenant && $tenant->crmSettings && $tenant->crmSettings->sender_name) {
            $fromName = $tenant->crmSettings->sender_name;
        } else {
            $fromName = $tenant?->name ?: 'Octiv';
        }

        $fromName = str_replace('"', '\\"', $fromName);

        /**
         * Switch Discovery user email to their alternate email.
         */
        if (str($to)->endsWith('@octiv.localhost')) {
            $to = User::query()->where('email', $to)->first()?->email_alt;

            if (! $to) {
                $reason = 'Could not switch Discovery users email.';
            }
        }

        if (! $replyTo || ! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = config('octiv.emails.noreply');
        }

        // filter out invalid emails
        if (isset($cc)) {
            $cc = array_filter($cc, function ($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });

            //remove duplicate CC addresses and to address
            $cc = collect($cc)
                ->unique()
                ->filter(fn ($email) => $email !== $to)
                ->toArray();
        }

        if (! $reason) {
            $reason = $this->getPotentialErrorMessageForMail(
                recipient: $to,
                tenant: $tenant ?? $location?->tenant,
                location: $location,
                notification: $notification,
                mailer: $mailer,
                skipUserChecks: $skipUserChecks
            );
        }

        if ($reason) {
            $user = User::query()->where('email', $to)->first();

            NotificationLog::create([
                'type' => NotificationLogType::EMAIL,
                'status' => NotificationLogStatus::CANCELLED,
                'recipient' => $to ?? 'null',
                'message' => $reason,

                'tenant_id' => $tenant?->getKey(),
                'location_id' => $location?->getKey(),
                'user_id' => $user?->getKey(),
                'notification_id' => $notification?->getKey(),
                'mailer_id' => $mailer?->getKey(),

                'title' => $subject,
                'content' => $content,

                'cc' => $cc,
                'reply_to' => $replyTo,
                'attachment' => $attach,
            ]);

            return false;
        }

        $mail = (new CrmMail(
            content: $content,
            fromName: $fromName,
            headerImage: $mailer ? $mailer->image_header_url : 'https://octiv-prod-public-newy2432.eu-central-1.linodeobjects.com/email-images/imageHeader.jpg',
            footerImage: $mailer?->image_footer_url,
            signature: $signature,
            attach: $attach
        ))->subject($subject)
            ->replyTo($replyTo)
            ->cc($cc)
            ->onQueue($queue);

        /** Attach single or multiple files */
        if ($attach) {

            $removeAttachments = false;

            if (! Arr::get($attach, 'disk')) {
                foreach ($attach as $attachment) {

                    if (Arr::get($attachment, 'delete_after_send')) {
                        $removeAttachments = true;
                    }

                    $mail->attachFromStorageDisk(
                        Arr::get($attachment, 'disk'),
                        Arr::get($attachment, 'path'),
                        Arr::get($attachment, 'filename')
                    );
                }
            } else {

                if (Arr::get($attach, 'delete_after_send')) {
                    $removeAttachments = true;
                }

                $mail->attachFromStorageDisk(
                    Arr::get($attach, 'disk'),
                    Arr::get($attach, 'path'),
                    Arr::get($attach, 'filename')
                );
            }

            if ($removeAttachments) {
                SendDispatchableMail::dispatch($mail->to($to))->chain([
                    new RemoveMailAttachments($attach),
                ]);

                return true;
            }
        }

        //send without removing attachments
        Mail::to($to)->send($mail);

        return true;
    }

    /**
     * Send mail from CRM notification.
     */
    public function createScheduledEmailForNotification(
        Tenant|Location $tenantOrLocation,
        string $context,
        mixed $recipient,
        ?array $cc = null,
        ?array $data = null,
        ?string $replyTo = null,
        ?array $attach = null,
        string $queue = 'low',
    ): bool {
        if (is_string($recipient)) {
            $to = $recipient;
        } else {
            $to = $recipient->email;
        }

        $isDiscoveryUser = $discoveryUser = str($to)->endsWith('@octiv.localhost');

        if ($isDiscoveryUser) {
            if (! in_array($context, [
                'discovery_booking_confirmation_member',
                'discovery_booking_cancelled_member',
                'discovery_booking_class_discontinued',
                'discovery_booking_modified_member',
                'discovery_booking_coach_cancelled_member',
            ])) {
                return false;
            }
        }

        if ($tenantOrLocation instanceof Tenant) {
            $tenant = $tenantOrLocation;
            $location = null;
        } else {
            $tenant = $tenantOrLocation->tenant;
            $location = $tenantOrLocation;
        }

        $replyTo = $replyTo ?? $this->getReplyTo($tenantOrLocation);

        $content = $this->getSystemNotificationContent($tenantOrLocation, $context, $data);
        $subject = $this->getSystemNotificationSubject($tenantOrLocation, $context, $data);

        $notification = Notifications::query()
            ->whereNotNull('system_type')
            ->whereNotNull('parent_id')
            ->where('box_id', $tenant->getKey())
            ->where('system_context', $context)
            ->where('status', '!=', 'deleted')
            ->orderBy('name')
            ->first();

        // Notifications CC
        if (! $discoveryUser && $notification && $notification->cc) {
            if (is_null($cc)) {
                $cc = [];
            }

            $cc = array_merge(
                $cc,
                array_map(
                    fn ($email) => trim($email),
                    explode(',', $notification->cc)
                )
            );
        }

        $this->createScheduledEmail(
            content: $content,
            subject: $subject,
            to: $to,
            replyTo: $replyTo,
            attach: $attach,
            cc: $cc,
            tenant: $tenant,
            location: $location,
            notification: $notification,
            queue: $queue
        );

        return true;
    }

    /**
     * Send user welcome notifications to user, box head coaches and facility admins.
     *
     * It is required to set box, facility and user before calling this method.
     */
    public function scheduleWelcomeMessageMailer(User $user, Tenant $tenant, bool $isSendImmediately = true, bool $isSignUp = false): void
    {
        $cc = TenantUser::query()
            ->with('user')
            ->whereHas('user')
            ->active()
            ->whereIn('user_type_id', [UserType::HEAD_COACH->value])
            ->where('box_id', $tenant->getKey())
            ->get()
            ->map(fn ($m) => $m->user->email)
            ->toArray();

        $location = LocationUser::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereRelation('location', 'box_id', '=', $tenant->getKey())
            ->active()
            ->latest('user_to_facility_id')
            ->first()
            ?->location;

        if (! $tenantUser = TenantUser::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('box_id', $tenant->getKey())
            ->withinActivePeriod()
            ->first()
        ) {
            throw new RuntimeException('This user does not have an active membership at the tenant.');
        }

        if ($location instanceof Location) {
            // CC the location admin if box has one or more
            $cc = array_merge(
                $cc,
                TenantUser::query()
                    ->active()
                    ->whereHas('user')
                    ->where('user_to_box.user_type_id', '=', UserType::BOX_FACILITY_ADMIN->value)
                    ->where('user_to_box.user_status_id', '=', UserStatus::ACTIVE->value)
                    ->where('user_to_box.box_id', $tenant->getKey())
                    ->join('user_to_facility', function ($query) use ($location) {
                        $query->on('user_to_facility.user_id', '=', 'user_to_box.user_id')
                            ->where('user_to_facility.box_facility_id', '=', $location->getKey())
                            ->where('user_to_facility.end_date', '>=', now()->toDateString());
                    })
                    ->get()
                    ->map(fn ($m) => $m->user->email)
                    ->toArray()
            );
        }

        $attachments = [];
        $isStaffMember = in_array($tenantUser->type->value, UserType::staffUserTypeIds());

        if (!$isStaffMember) {
            $contract = $user->latestContract($tenant)->first();

            if ($contract) {
                if ($contract->file_path) {
                    $attachments[] = [
                        'disk' => 'public',
                        'path' => $contract->file_path,
                        'filename' => $contract->file_name,
                    ];
                }
                $path = (new UserContractService())->generateContractPdf($contract);

                $attachments[] = [
                    'disk' => 'tmp',
                    'path' => $path,
                    'filename' => basename($path),
                ];
            }
        }

        $this->createScheduledEmailForNotification(
            tenantOrLocation: $location ?: $tenant,
            context: 'sign_up_immediately',
            recipient: $user,
            cc: $cc,
            data: [
                'user_name' => $user->name,
                'user_surname' => $user->surname,
                'facility_name' => $tenant->name,
                'location_name' => $location?->name ?: $tenant->name,
            ],
            attach: $attachments,
            queue: $isSendImmediately ? 'high' : 'low'
        );

        $this->sendPasswordResetWelcomeMail($user, $tenant, $location, $isSendImmediately, $isSignUp, $tenantUser->isMember());

        //notify head coaches of new registration
        if (! empty($cc)) {
            $firstHeadCoach = $cc[0];
            unset($cc[0]);

            $userPackageNames = $user->packages()
                ->where('box_id', '=', $tenant->getKey())
                ->where('package_limit_type_id', '!=', 4)
                ->where('user_to_package.deleted', '=', false)
                ->pluck('package_name')
                ->join(', ');

            if ($isStaffMember) {
                $content = Markdown::parse(
                    view('emails.new-staff-member', [
                        'user' => $user,
                        'userType' => $tenantUser->type->toString(),
                        'location' => $location?->name,
                    ])->render()
                )->__toString();
            } else {
                $content = Markdown::parse(
                    view('emails.new-athlete', [
                        'user' => $user,
                        'userPackages' => $userPackageNames,
                        'paymentType' => $tenantUser->debit_status?->toString(),
                        'location' => $location?->name,
                    ])->render()
                )->__toString();
            }

            $this->createScheduledEmail(
                content: $content,
                subject: 'New Member Registration',
                to: $firstHeadCoach,
                cc: $cc
            );
        }
    }

    /**
     * Send welcome mail to user with password reset link.
     *
     * It is required to set box, facility and user before calling this method.
     */
    public function sendPasswordResetWelcomeMail(User $user, Tenant $tenant, ?Location $location = null, bool $isSendImmediately = true, bool $isSignUp = false, bool $isMember = true): void
    {
        DB::table('password_resets')->insert([
            'email' => $user->email,
            'token' => $token = Password::createToken($user),
            'created_at' => now(),
        ]);

        $passwordResetLink = config('octiv.web_app_url').'/reset-password/?token='.$token.'&email='.urlencode($user->email);

        $content = Markdown::parse(
            view('emails.welcome', [
                'memberName' => $user->name,
                'email' => $user->email,
                'boxName' => $tenant->name,
                'locationName' => $location?->name ?: $tenant->name,
                'passwordResetLink' => $passwordResetLink,
                'isMember' => $isMember,
                'isSignUp' => $isSignUp,
            ])->render()
        )->__toString();

        $this->createScheduledEmail(
            content: $content,
            subject: 'Welcome to '.$tenant->name,
            to: $user->email,
            replyTo: $this->getReplyTo($location ?: $tenant),
            tenant: $tenant,
            location: $location,
            queue: $isSendImmediately ? 'high' : 'low'
        );
    }

    public function sendNoFutureBatchesNotifications(User $user, Location $location, UserInvoice $invoice, ?int $locationPaymentGatewayId = null): void
    {
        $paymentLink = config('octiv.web_app_url').'/payment/'.$invoice->getKey();

        if ($locationPaymentGatewayId) {
            $paymentLink .= "?gid=$locationPaymentGatewayId";
        }

        $content = Markdown::parse(
            view('emails.finance.sign-up-no-debit-batch-member', [
                'memberName' => $user->full_name,
                'invoiceUrl' => $paymentLink,
            ])->render()
        )->__toString();

        $this->createScheduledEmail(
            content: $content,
            subject: 'Invoice for Payment',
            to: $user->email,
            replyTo: $this->getReplyTo($location),
            tenant: $location->tenant,
            location: $location,
            queue: 'high'
        );

        $coachesCcArray = $this->getCoachesForNotificationCcArray($location);

        $coachEmailContent = Markdown::parse(
            view('emails.finance.sign-up-no-debit-batch-coach', [
                'memberName' => $user->full_name,
                'invoiceUrl' => config('octiv.web_app_url').'/accounts/invoices?invoiceId='.$invoice->getKey(),
            ])->render()
        )->__toString();

        if (count($coachesCcArray) > 0) {
            $firstCoach = $coachesCcArray[0];
            unset($coachesCcArray[0]);

            $this->createScheduledEmail(
                content: $coachEmailContent,
                subject: 'Cash invoice generated for package',
                to: $firstCoach,
                cc: $coachesCcArray,
                tenant: $location->tenant,
                location: $location,
                queue: 'high'
            );
        }
    }

    private function getPotentialErrorMessageForMail(
        ?string $recipient,
        ?Tenant $tenant,
        ?Location $location = null,
        ?Notifications $notification = null,
        ?Mailer $mailer = null,
        $skipUserChecks = false
    ): ?string {
        $tenantUser = null;
        $user = User::query()->where('email', $recipient)->first();
        $adminSettings = CrmSetting::query()->whereNull('box_id')->whereNull('box_facility_id')->first();

        $tenantId = $tenant?->getKey();

        if ($tenant && ! $tenant->isActive()) {
            return 'Inactive tenant.';
        }

        if ($user && $tenantId) {
            $tenantUser = TenantUser::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('box_id', $tenantId)
                ->withinActivePeriod()
                ->first();
        }

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email address.';
        }

        if (Validator::make(['email' => $recipient], ['email' => 'email:rfc'])->fails()) {
            return 'Email does not comply with RFC spec.';
        }

        if ($adminSettings?->email_status === 'disabled') {
            // 1 - Check if the global emails are active
            return 'Cancelled due to super admin emails being disabled.';
        }

        if ($location?->crmSettings?->email_status === 'disabled') {
            // 2 - Check box facility emails are active
            return 'Cancelled due to location emails being disabled.';
        }

        if ($notification?->status === NotificationStatus::DISABLED) {
            // 3 - Check if email is for notification and system mailer has been disabled by admin or box
            return 'Cancelled due to notification being disabled by tenant.';
        }

        if ($notification && $notification->parent && $notification->parent->status === NotificationStatus::DELETED) {
            // 4 - Check if email is for notification and system mailer has been disabled by admin or box
            return 'Cancelled due to notification being deleted.';
        }

        if ($mailer?->deleted) {
            // 5 - Check if mailer has been deleted
            return 'Cancelled due to the mailer being deleted.';
        }

        if (! $skipUserChecks) {
            if ($tenantUser && $tenantUser->status === UserStatus::DEACTIVATED && ! $mailer) {
                // 6 - Check if recipient (must be system user) is deactivated
                return 'Cancelled due to user being deactivated.';
            }

            if ($notification && $user && $user->isUnsubscribedFromNotification($notification)) {
                // 7 - Check if email is for notification and recipient (must be system user) has unsubscribed from email
                return 'Cancelled due to user unsubscribing from this notification.';
            }
        }

        return null;
    }

    /**
     * Returns an error message if a user is not allowed to send a push notification.
     */
    public function getPotentialErrorMessageForPushNotification(User $user): ?string
    {
        // 1 - Check if user has a push notification token
        if (empty($user->pushNotificationToken())) {
            return 'Cancelled due to user not having a push token.';
        }

        //TODO: Scope this to the current tenant
        // 2 - Check if recipient (must be system user) is deactivated
        if (! TenantUser::query()
            ->memberships()
            ->withinActivePeriod()
            ->where('user_id', $user->getAuthIdentifier())
            ->exists()
        ) {
            return 'Cancelled due to user being deactivated.';
        }

        return null;
    }

    private function getPotentialErrorMessageForSms(?Location $location, string $mobile): bool|string
    {
        $adminSettings = CrmSetting::query()->whereNull('box_id')->whereNull('box_facility_id')->first();

        if ($adminSettings?->sms_status === 'disabled') {
            // 1 - Check if the global emails are active
            return 'Cancelled due to super admin SMS being disabled.';
        }

        if ($location) {
            if (! $location->crmSettings) {
                return 'Location or settings do not exist';
            }

            if ($location->crmSettings->sms_status === 'disabled') {
                return 'Location sms disabled';
            }
        }

        if (! is_numeric($mobile)) {
            return 'Mobile number format invalid';
        }

        return true;
    }

    public function createScheduledEmailForMailer(Mailer $mailer, $tenantOrLocation): void
    {
        if ($mailer->type !== MailerType::EMAIL) {
            throw new RuntimeException('Cannot process non-email mailer.');
        }

        $mailer->loadMissing('recipients', 'attachments');

        // gather resources
        $tenant = null;
        $location = null;

        // box and facility
        if ($tenantOrLocation instanceof Location) {
            $tenant = $tenantOrLocation->tenant;
            $location = $tenantOrLocation;
        } elseif ($tenantOrLocation instanceof Tenant) {
            $tenant = $tenantOrLocation;
        }

        // REPLY-TO - best-case scenario, use the mailer setting
        if ($mailer->reply_to) {
            $replyTo = $mailer->reply_to;
        } // REPLY-TO -  best case, use facility setting
        elseif ($location && $location->crmSettings && $location->crmSettings->reply_to) {
            $replyTo = $location->crmSettings->reply_to;
        } else {
            $replyTo = $this->getHeadCoachEmailOrDefaultReplyTo($tenant);
        }

        foreach ($mailer->recipients as $recipient) {
            if (! $data = $recipient->toNameArray()) {
                continue;
            }

            // content, subject and notification
            $content = $mailer->content ? $this->injectData($mailer->content, $data) : '';
            $subject = $mailer->subject ? $this->injectData($mailer->subject, $data) : '';

            $attachments = $mailer->attachments->map(fn ($attachment) => [
                'disk' => 'private',
                'path' => $attachment->attachment_path,
                'filename' => $attachment->attachment_name,
            ])->toArray();

            $this->createScheduledEmail(
                content: $content,
                subject: $subject,
                to: $recipient->getMailToAddress(),
                replyTo: $replyTo,
                attach: $attachments,
                tenant: $tenant,
                location: $location,
                mailer: $mailer
            );
        }
    }

    private function injectData($string, $data): string
    {
        // inject variable content
        foreach ($data as $key => $content) {
            $string = str_replace('['.$key.']', $content, $string);
        }

        return $string;
    }

    public function createScheduledSMSForMailer(Mailer $mailer, $tenantOrLocation): void
    {
        if ($mailer->type !== MailerType::SMS) {
            throw new RuntimeException('Cannot process non-SMS mailer.');
        }

        $mailer->loadMissing('recipients');

        // gather resources
        $tenant = null;
        $location = null;

        // box and facility
        if ($tenantOrLocation instanceof Location) {
            $tenant = $tenantOrLocation->tenant;
            $location = $tenantOrLocation;
        } elseif ($tenantOrLocation instanceof Tenant) {
            $tenant = $tenantOrLocation;
        }

        foreach ($mailer->recipients as $recipient) {
            if (! $to = $recipient->getMobileNumber()) {
                continue;
            }

            $this->createScheduledSms(
                content: $mailer->content,
                to: $to,
                tenant: $tenant,
                location: $location,
                mailer: $mailer
            );
        }
    }

    public function createScheduledPushForMailer(Mailer $mailer, $tenantOrLocation): void
    {
        if ($mailer->type !== MailerType::PUSH) {
            throw new RuntimeException('Cannot process non-push mailer.');
        }

        // gather resources
        $tenant = null;
        $location = null;

        // box and facility
        if ($tenantOrLocation instanceof Location) {
            $tenant = $tenantOrLocation->tenant;
            $location = $tenantOrLocation;
        } elseif ($tenantOrLocation instanceof Tenant) {
            $tenant = $tenantOrLocation;
        }

        // loop through members
        foreach ($mailer->recipients as $recipient) {
            if (! $recipient->user instanceof User) {
                Log::error("Cannot send push notification to non-user for recipient: {$recipient->getKey()}.");

                continue;
            }

            $this->createScheduledPushNotification(
                title: $mailer->title,
                content: $mailer->content,
                user: $recipient->user,
                tenant: $tenant,
                location: $location,
                mailer: $mailer
            );
        }
    }

    /**
     * This is if no reply-to was found on the box facility or box. This is the last resort
     */
    public function getHeadCoachEmailOrDefaultReplyTo(Tenant $tenant): string
    {
        $headCoaches = TenantUser::query()
            ->with('user')
            ->withinActivePeriod()
            ->headCoaches()
            ->where('user_status_id', '!=', UserStatus::DEACTIVATED->value)
            ->where('box_id', $tenant->getKey())
            ->get();

        if ($headCoaches->isNotEmpty()) {
            return $headCoaches->first()->user->email;
        }

        return config('octiv.emails.noreply');
    }

    public function getEmailSignatureContent(Tenant|Location $tenantOrLocation): string
    {
        $crmSettings = $tenantOrLocation->crmSettings;
        $signature = 'Yours in Fitness,<br />Team Octiv';

        if ($crmSettings && $crmSettings->email_signature != '') {
            $signature = $crmSettings->email_signature;
        }

        return $signature;
    }

    public function getUsersMailingLists(Tenant $tenant): Collection
    {
        return CrmMailingList::query()
            ->where('box_id', $tenant->getKey())
            ->get();
    }

    public function addUserToAllActiveMemberMailingLists(User $user, Tenant $tenant): void
    {
        $tenantUserService = (new TenantUserService());
        $mailingLists = CrmMailingList::query()->where('box_id', '=', $tenant->getKey())->get();

        /** @var CrmMailingList $mailingList */
        foreach ($mailingLists as $mailingList) {
            $filters = $mailingList->filters;
            $activeMailer = false;

            if (! isset($filters['userStatuses'])) {
                continue;
            }

            $userStatusesByFacility = $filters['userStatuses'];
            $memberByFacility = $filters['members'];

            foreach ($userStatusesByFacility as $facilityStatuses) {
                foreach ($facilityStatuses as $status) {
                    if ($status === 'active') {
                        $activeMailer = true;
                    }
                }
            }

            if ($activeMailer) {
                $locationUser = $tenantUserService->getLocationUserByTenant($user, $tenant);

                if ($locationUser?->location) {
                    foreach ($memberByFacility as $locationId => $locationMembers) {
                        if ($locationUser->location->getKey() === (int) $locationId) {
                            $locationMembers[] = $user->getKey();
                        }

                        $memberByFacility[$locationId] = $locationMembers;
                    }
                }
            }

            $filters['userStatuses'] = $userStatusesByFacility;
            $filters['members'] = $memberByFacility;

            $mailingList->update(['filters' => $filters]);
        }
    }

    public function removeUserFromAllMailingLists(User $user, Tenant $tenant): void
    {
        $usersMailingLists = $this->getUsersMailingLists($tenant);

        /** @var CrmMailingList $mailingList */
        foreach ($usersMailingLists as $mailingList) {
            $filters = $mailingList->filters;

            if (! isset($filters['members'])) {
                continue;
            }

            $facilities = $filters['members'];

            foreach ($facilities as $facilityId => $facilityMembers) {
                foreach ($facilityMembers as $key => $id) {
                    if ((int) $id === $user->getAuthIdentifier()) {
                        unset($facilityMembers[$key]);
                    }
                }

                $facilities[$facilityId] = $facilityMembers;
            }

            $filters['members'] = $facilities;

            $mailingList->filters = $filters;
            $mailingList->save();
        }
    }

    public function removeUserBoxMembershipRecipients(TenantUser $tenantUser): void
    {
        $recipients = MailerRecipient::query()
            ->join('crm_mailers', 'crm_recipients.mailer_id', '=', 'crm_mailers.mailer_id')
            ->join('box_facility', 'box_facility.box_facility_id', 'crm_mailers.box_facility_id')
            ->where('box_facility.box_id', $tenantUser->tenant_id)
            ->where('crm_recipients.ref_entity_id', $tenantUser->user_id) //TODO: Check ID requirements
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->delete();
        }
    }
}
