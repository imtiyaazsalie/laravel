<?php

namespace App\Services;

use App\Enums\LeadMemberStatus;
use App\Enums\LeadMemberType;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\WaiverStatus;
use App\Models\LeadMember;
use App\Models\LeadWaivers;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

class LeadService
{
    public function getLeadMembersQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(LeadMember::class)
            ->distinct()
            ->select('lead_members.*')
            ->join('users', 'lead_members.user_id', 'users.user_id')
            ->join('box_facility', 'lead_members.box_facility_id', 'box_facility.box_facility_id')
            ->join('user_to_box', function (JoinClause $join) {
                $join->on('user_to_box.box_id', '=', 'box_facility.box_id')
                    ->on('user_to_box.user_id', '=', 'lead_members.user_id');
            })
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_facility.box_id'),
                AllowedFilter::exact('location_id', 'box_facility_id'),
                AllowedFilter::exact('is_redacted', 'users.is_redacted'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::scope('search', 'user.search'),
            ])
            ->allowedIncludes(
                'classBookings',
                'referredBy',
                'capturedBy',
                'waiver',
            )
            ->allowedSorts(
                AllowedSort::callback('name', function (Builder $query, $isDescending) {
                    $sortDirection = $isDescending ? SortDirection::DESCENDING : SortDirection::ASCENDING;

                    $query
                        ->orderBy('users.name', $sortDirection)
                        ->orderBy('users.surname', $sortDirection);
                }),
                AllowedSort::field('email', 'users.email'),
                AllowedSort::field('mobile', 'users.mobile'),
                AllowedSort::field('last_contacted_at', 'lead_members.last_contacted_date'),
                AllowedSort::field('next_follow_up_at', 'lead_members.next_follow_up_date'),
                AllowedSort::field('captured', 'lead_members.created_on'),
            )
            ->where('lead_members.deleted', false)
            ->where('box_facility.is_active', true)
            ->where('user_to_box.deleted', false)
            ->groupBy('lead_members.user_id')
            ->when(! request()->input('filter.status'), function (Builder $query) {
                $query->where('status', '!=', LeadMemberStatus::CONVERTED);
            })
            ->defaultSort('-lead_members.created_on');
    }

    public function createLeadUserMembership(Request $request, ?User $user = null): LeadMember
    {
        $location = Location::find($request->input('location_id'));

        if (! $user instanceof User) {
            $user = User::create([
                ...$request->safe()->only([
                    'name',
                    'surname',
                    'email',
                    'mobile',
                    'date_of_birth',
                    'gender_id',
                ]),
                'user_type_id' => UserType::USER,
                'user_status_id' => UserStatus::ACTIVE,
            ]);
        }

        $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($user, $location->tenant_id);

        if ($userTenant && $userTenant->type !== UserType::LEAD_MEMBER) {
            abort(400, 'This user already has an active membership at this facility.');
        }

        $leadMember = LeadMember::firstOrCreate([
            'user_id' => $user->getKey(),
            'box_facility_id' => $location->getKey(),
            'deleted' => false,
        ], [
            'status' => $request->has('status') ? $request->input('status') : LeadMemberStatus::PENDING,
            'source' => $request->input('source'),
            'last_contacted_date' => $request->input('last_contacted_at'),
            'next_follow_up_date' => $request->input('next_follow_up_at'),
            'notes' => $request->input('notes'),
            'referred_by_id' => $request->input('referred_by_id'),
            'type' => LeadMemberType::REFERRAL,
        ]);

        // Create Lead Memberships
        if (! $userTenant) {
            (new TenantUserService())->createUserBoxMembership(
                user: $user,
                tenant: $location->tenant,
                userType: UserType::LEAD_MEMBER,
                startingDate: Carbon::now(),
                userDebitStatus: UserDebitStatus::NO_PAYMENT,
                userStatus: UserStatus::ACTIVE
            );
        }

        $locationUser = (new TenantUserService())->getLocationLeadByTenant($leadMember, $location->tenant_id);

        if (! $locationUser) {
            (new TenantUserService())->createUserFacilityMembership(
                $leadMember,
                $location,
                Carbon::now()
            );
        }

        return $leadMember;
    }

    public function sendWaiver(string|int $tenantId, LeadWaivers $tenantWaiver, User $user, ?LeadWaivers $waiver, bool $isLatest): void
    {
        $location = (new TenantUserService())->getLocationUserByTenant($user, $tenantId)?->location;

        if (! $location) {
            return;
        }

        $crmService = resolve(CrmService::class);

        if ($tenantWaiver->isDigital()) {
            if (! $waiver) {
                $waiver = LeadWaivers::create([
                    'digital' => $tenantWaiver->digital,
                    'digital_terms_and_conditions' => $tenantWaiver->digital_terms_and_conditions,
                    'parent_id' => $tenantWaiver->getKey(),
                    'location_id' => $location->getKey(),
                    'user_id' => $user->getAuthIdentifier(),
                ]);
            } elseif ($isLatest) {
                $waiver->update([
                    'digital_terms_and_conditions' => $tenantWaiver->digital_terms_and_conditions,
                    'parent_id' => $tenantWaiver->getKey(),
                    'signed_on' => null,
                ]);
            }

            $waiver->update(['status' => WaiverStatus::SENT]);

            $url = config('octiv.web_app_url').'/sign/waiver/'.$waiver->getKey();

            $crmService->createScheduledEmailForNotification(
                tenantOrLocation: $location,
                context: 'send_waiver',
                recipient: $user,
                data: [
                    'member_name' => $user->name,
                    'member_surname' => $user->surname,
                    'link' => "<a href='$url'>$url</a>",
                ]
            );
        } elseif ($tenantWaiver->file_path) {
            $fileName = $user->name.'_waiver-document.'.str($tenantWaiver->file_path)->afterLast('.')->toString();

            if (Storage::disk('private')->exists($tenantWaiver->file_path)) {
                $crmService->createScheduledEmailForNotification(
                    tenantOrLocation: $location,
                    context: 'send_waiver_attachment',
                    recipient: $user,
                    data: [
                        'member_name' => $user->name,
                        'member_surname' => $user->surname,
                    ],
                    attach: [
                        'disk' => 'private',
                        'path' => $tenantWaiver->file_path,
                        'filename' => $fileName,
                        'mimetype' => $tenantWaiver->file_mime,
                    ]
                );

            } else {
                Log::error('Trying to send waiver with missing attachment.', [
                    'waiver_id' => $tenantWaiver->getKey(),
                ]);
            }
        }
    }

    public function notifyCoachOfNewLead(LeadMember $leadMember, bool $isRequestDemo): void
    {
        $location = $leadMember->location;

        $message = 'Hi coach,<br /><br />';

        if ($isRequestDemo) {
            $message .= 'A new lead has requested a demo using the widget.<br /><br />';
        } else {
            $message .= 'A new lead has signed up using the widget.<br /><br />';
        }

        $message .= 'Lead details:<br /><br />';
        $message .= "Name: {$leadMember->user->full_name}<br />";
        $message .= "Email: {$leadMember->user->email}<br />";

        if ($leadMember->user->dob) {
            $message .= "DOB: {$leadMember->user->dob->format('Y-m-d')}<br />";
        }

        if ($leadMember->user->mobile) {
            $message .= "Mobile number: {$leadMember->user->mobile}<br />";
        }

        $message .= "Location: {$location->name}<br />";

        if ($leadMember->notes) {
            $message .= "Notes: {$leadMember->notes}";
        }

        // Default reply to
        $replyTo = 'noreply@octivfitness.com';
        $toArray = [];
        $ccArray = [];

        $crmSettings = $location->crmSettings;

        // Send to all facility admins or to head coach if facility has no facility admins
        $headCoaches = $location->tenant
            ->headCoaches()
            ->where('user_to_box.end_date', '>', today()->toDateString())
            ->where('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED)
            ->get();

        $locationAdmins = User::query()
            ->select('users.*')
            ->distinct()
            ->join('user_to_facility', 'user_to_facility.user_id', '=', 'users.user_id')
            ->join('user_to_box', 'user_to_box.user_id', '=', 'users.user_id')
            ->where('user_to_facility.box_facility_id', $location->getKey())
            ->where('user_to_box.user_status_id', UserStatus::ACTIVE)
            ->where('user_to_box.user_type_id', UserType::BOX_FACILITY_ADMIN)
            ->where('user_to_facility.end_date', '>', today())
            ->orderBy('users.name')
            ->get();

        if (count($locationAdmins) > 0) {
            /** @var User $boxFacilityAdmin */
            foreach ($locationAdmins as $locationAdmin) {
                $toArray[] = $locationAdmin->email;
            }
        } else {
            if (isset($headCoaches) && count($headCoaches) > 0) {
                $firstHeadCoach = $headCoaches->first();

                $toArray[] = $firstHeadCoach->email;

                // Cc all head coaches
                foreach ($headCoaches as $index => $headCoach) {
                    // Skip the first coach
                    if ($index == 0) {
                        continue;
                    }

                    $ccArray[] = $headCoach->email;
                }
            }
        }

        // Get cc email addresses.
        if ($crmSettings && $crmSettings->getReplyTo() && ! in_array($crmSettings->getReplyTo(), $toArray) && ! in_array($crmSettings->getReplyTo(), $ccArray)) {
            // CC the facility reply to email address
            $ccArray[] = $crmSettings->getReplyTo();
        }

        $send = true;

        if (empty($toArray)) {
            if (! empty($ccArray) && count($ccArray) > 0) {
                $toArray[] = $ccArray[0];
            } else {
                $send = false;
            }
        }

        if ($send) {
            foreach ($toArray as $to) {
                (new CrmService())->createScheduledEmail(
                    content: $message,
                    subject: 'Octiv: New Lead',
                    to: $to,
                    replyTo: $replyTo,
                    cc: $ccArray
                );
            }
        }
    }
}
