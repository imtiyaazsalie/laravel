<?php

namespace App\Http\Controllers\API;

use App\Enums\InvoiceDiscriminator;
use App\Enums\LeadMemberStatus;
use App\Enums\LeadMemberType;
use App\Enums\PackageType;
use App\Enums\UserType;
use App\Enums\WaiverStatus;
use App\Exports\LeadMembersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\BulkDeleteUserTenantRequest;
use App\Http\Requests\Leads\BulkStatusChangeUserTenantRequest;
use App\Http\Requests\Leads\CancelClassBookingRequest;
use App\Http\Requests\Leads\ConvertLeadMemberRequest;
use App\Http\Requests\Leads\CreateClassBookingRequest;
use App\Http\Requests\Leads\CreateDropInRequest;
use App\Http\Requests\Leads\CreateLeadMemberRequest;
use App\Http\Requests\Leads\ExportLeadMembersRequest;
use App\Http\Requests\Leads\GenerateLeadInvoiceRequest;
use App\Http\Requests\Leads\GetDropInPackagesRequest;
use App\Http\Requests\Leads\ImportLeadMembersRequest;
use App\Http\Requests\Leads\ListLeadMembersRequest;
use App\Http\Requests\Leads\SignUpLeadMemberRequest;
use App\Http\Requests\Leads\UpdateLeadMemberRequest;
use App\Http\Resources\ClassBookingResource;
use App\Http\Resources\LeadMemberResource;
use App\Http\Resources\UserInvoiceResource;
use App\Http\Resources\UserPackageResource;
use App\Imports\LeadUsersImport;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\LeadMember;
use App\Models\LeadSettings;
use App\Models\LeadWaivers;
use App\Models\Location;
use App\Models\LocationUser;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Timezone;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\ClassService;
use App\Services\CrmService;
use App\Services\InvoiceService;
use App\Services\LeadService;
use App\Services\PaymentGateways\PaymentGatewayService;
use App\Services\TenantUserService;
use App\Services\WidgetService;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Maatwebsite\Excel\Facades\Excel;

class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leadService)
    {
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[status]', 'string', required: false)]
    #[QueryParam('filter[type]', 'string', required: false)]
    #[QueryParam('filter[is_redacted]', 'boolean', required: false)]
    #[QueryParam('filter[search]', 'string', required: false)]
    #[QueryParam('page', 'string', required: false)]
    #[QueryParam('per_page', 'string', required: false)]
    public function listLeadMembers(ListLeadMembersRequest $request)
    {
        return LeadMemberResource::collection($this->leadService->getLeadMembersQueryBuilder()->_paginate());
    }

    public function store(CreateLeadMemberRequest $request)
    {
        $user = User::where('email', '=', $request->input('email'))->first();

        if ($user) {
            if ((new TenantUserService())->getCurrentUserTenantForTenant($user, $request->input('tenant_id'))?->isMember()) {
                abort(400, 'User already exists as a member at this gym or is currently a lead at this facility.');
            }
        }

        return LeadMemberResource::make($this->leadService->createLeadUserMembership($request, $user));
    }

    public function update(UpdateLeadMemberRequest $request, LeadMember $leadMember)
    {
        $location = Location::find($request->input('location_id'));
        $hasLocationChange = $location->getKey() !== $leadMember->location_id;

        if ($hasLocationChange) {
            $locationMember = LeadMember::query()
                ->where('user_id', $leadMember->user_id)
                ->where('deleted', '=', '0')
                ->where('box_facility_id', $location->getKey())
                ->exists();
            if ($locationMember) {
                abort(400, 'A lead for this user already exists at the requested location.');
            }
        }

        $leadMember->update([
            'location_id' => $location->getKey(),
            'status' => $request->input('status'),
            'source' => $request->input('source'),
            'last_contacted_date' => $request->input('last_contacted_at'),
            'next_follow_up_date' => $request->input('next_follow_up_at'),
            'notes' => $request->input('notes'),
            'referred_by_id' => $request->input('referred_by_id'),
        ]);

        if ($hasLocationChange) {
            $leadLocation = (new TenantUserService())->getLocationLeadByTenant($leadMember, $location->tenant);
            $leadLocation?->update(['end_date' => now()->subDay()]);

            (new TenantUserService())->createUserFacilityMembership(
                $leadMember,
                $location,
                Carbon::now()
            );
        }

        $leadMember->user->update([
            'name' => $request->input('name'),
            'surname' => $request->input('surname'),
            'date_of_birth' => $request->input('date_of_birth'),
            'gender_id' => $request->input('gender_id'),
        ]);

        return LeadMemberResource::make($leadMember);
    }

    public function exportLeadMembers(ExportLeadMembersRequest $request): JsonResponse
    {
        $date = Carbon::now()->format('Y-m-d');
        $tenant = Tenant::query()->find($request->input('filter.tenant_id'));
        $location = Location::query()->find($request->input('filter.location_id'));

        if ($location && $request->has('filter.tenant_id')) {
            $filepath = "leads-exports/{$tenant->name}_{$location->name}_leads_$date.csv";
        } else {
            $filepath = "leads-exports/{$tenant->name}_leads_$date.csv";
        }

        Excel::store(new LeadMembersExport(), $filepath, 'tmp', \Maatwebsite\Excel\Excel::CSV);

        return response()->json([
            'file' => Storage::disk('tmp')->temporaryUrl($filepath, now()->addMinutes(5)),
        ]);
    }

    public function importLeadMembers(ImportLeadMembersRequest $request)
    {
        $import = new LeadUsersImport();
        Excel::import($import, $request->file('file'));

        return response()->json($import->result());
    }

    public function generateLeadInvoice(GenerateLeadInvoiceRequest $request, LeadMember $leadMember): JsonResponse|UserInvoiceResource
    {
        $invoice = (new InvoiceService())->generateInvoiceForLeadMember($leadMember, $request->get('amount'), \Illuminate\Support\Carbon::parse($request->get('due_on')));

        if ($request->get('is_send') === true) {
            (new InvoiceService())->sendInvoice($invoice);
        }

        return new UserInvoiceResource($invoice);
    }

    public function bulkStatusChange(BulkStatusChangeUserTenantRequest $request)
    {
        $authUser = (new TenantUserService)->getCurrentUserTenantForTenant(auth()->user(), $request->tenant_id);

        foreach ($request->input('lead_member_ids') as $leadMemberId) {
            $leadMember = LeadMember::query()->find($leadMemberId);

            if (! $leadMember) {
                continue;
            }

            if ($leadMember->location->tenant_id !== $authUser->tenant_id) {
                continue;
            }

            if ($leadMember->status->value == $request->input('status')) {
                continue;
            }

            $leadMember->update([
                'status' => $request->input('status'),
            ]);
        }

        return response()->noContent();
    }

    public function bulkDelete(BulkDeleteUserTenantRequest $request)
    {
        foreach ($request->input('lead_member_ids') as $leadMemberId) {
            $leadMember = LeadMember::query()->find($leadMemberId);

            if (! $leadMember) {
                continue;
            }

            if ($leadMember->location->tenant_id != $request->tenant_id) {
                continue;
            }

            $userTenants = TenantUser::query()
                ->where('user_id', '=', $leadMember->user_id)
                ->where('box_id', '=', $request->tenant_id)
                ->where('user_type_id', '=', UserType::LEAD_MEMBER)
                ->get();

            foreach ($userTenants as $userTenant) {
                $userTenant?->update(['deleted' => true]);
            }
            $leadMember->update(['deleted' => true]);
        }

        return response()->noContent();
    }

    public function convertLeadToGymMember(ConvertLeadMemberRequest $request, LeadMember $leadMember)
    {
        if ($leadMember->user->is_redacted) {
            abort(404, 'Discovery lead members cannot be converted, please create a new user instead.');
        }

        if ($leadMember->location_id != $request->input('location_id')) {
            abort(400, 'This lead member does not belong to this facility.');
        }

        if ((new TenantUserService())->getCurrentUserTenantForTenant($leadMember->user, $leadMember->location->tenant_id)?->isMember()) {
            abort(400, 'User already exists as a member at this gym under a different facility.');
        }

        $locationUser = LocationUser::query()
            ->where('user_id', $leadMember->user_id)
            ->where('box_facility_id', '=', $request->input('location_id'))
            ->first();

        if (! $locationUser) {
            (new TenantUserService())->createUserFacilityMembership(
                $leadMember->user,
                Location::query()->find($request->input('location_id')),
                Carbon::now()
            );
        }

        $leadMember->update([
            'status' => LeadMemberStatus::CONVERTED,
            'converted_on' => now(),
        ]);

        $userTenant = TenantUser::query()
            ->where('user_id', '=', $leadMember->user_id)
            ->where('box_id', '=', $leadMember->location->tenant_id)
            ->where('user_type_id', '=', UserType::LEAD_MEMBER)
            ->first();

        $userTenant?->update(['type' => UserType::GYM_MEMBER]);

        (new CrmService())->scheduleWelcomeMessageMailer($leadMember->user, $leadMember->location->tenant);

        return response()->json([
            'user_id' => $leadMember->user_id,
            'user_tenant_id' => $userTenant?->getKey(),
        ]);
    }

    public function signUp(SignUpLeadMemberRequest $request)
    {
        $isRequestDemo = ($request->has('is_request_demo') && (int) $request->input('is_request_demo') === 1);

        $user = User::where('email', '=', $request->input('email'))->first();

        if ($user) {
            if ((new TenantUserService())->getCurrentUserTenantForTenant($user, $request->input('tenant_id'))?->isMember()) {
                abort(400, 'User already exists as a member at this gym or is currently a lead at this facility.');
            }
        }

        $leadMember = $this->leadService->createLeadUserMembership($request, $user);

        $leadMember->update([
            'type' => LeadMemberType::WEBSITE,
            'status' => $isRequestDemo ? LeadMemberStatus::REQUEST_DEMO : LeadMemberStatus::PENDING,
        ]);

        $tenant = $leadMember->location->tenant;

        if ($isRequestDemo) {
            $leadSettings = $tenant->leadSettings;

            if ($leadSettings instanceof LeadSettings) {
                $tenantWaiver = $leadSettings->waiver;

                if ($tenantWaiver->isDigital()) {
                    $waiver = LeadWaivers::create([
                        'box_facility_id' => $leadMember->location_id,
                        'digital' => $tenantWaiver->isDigital(),
                        'digital_terms_and_conditions' => $tenantWaiver->digital_terms_and_conditions,
                        'parent_id' => $tenantWaiver->getKey(),
                        'status' => WaiverStatus::SIGNED,
                        'signed_on' => now(),
                        'ip_address' => $request->getClientIp(),
                        'user_id' => $leadMember->user_id,
                    ]);

                    $leadMember->update([
                        'waiver_id' => $waiver->getKey(),
                    ]);
                }
            }
        }

        // Notify coach of new lead
        $this->leadService->notifyCoachOfNewLead($leadMember, $isRequestDemo);

        // Notify member
        $systemContextForEmail = $isRequestDemo ? 'member_lead_received_demo_request' : 'member_lead_received';

        // Create a scheduled email. Auto responder to member that lead is being processed
        (new CrmService())->createScheduledEmailForNotification(
            tenantOrLocation: $leadMember->location,
            context: $systemContextForEmail,
            recipient: $leadMember->user->email,
            data: [
                'member_name' => $leadMember->user->name,
                'member_surname' => $leadMember->user->surname,
            ]
        );

        if ($isRequestDemo) {
            $data['redirectUrl'] = ! empty($tenant->lead_request_demo_redirect_url) ? $tenant->lead_request_demo_redirect_url : 'https://octivfitness.com';
        } else {
            $data['redirectUrl'] = ! empty($tenant->lead_redirect_url) ? $tenant->lead_redirect_url : 'https://octivfitness.com';
        }

        return response()->json($data, 201);
    }

    public function createDropIn(CreateDropInRequest $request)
    {
        $tenant = Tenant::findOrFail($request->input('tenant_id'));
        $selectedLocation = Location::findOrFail($request->input('location_id'));

        if ($tenant->getKey() !== $selectedLocation->tenant_id) {
            abort(403, 'Location does not belong to this tenant.');
        }

        $dropInPackage = Package::query()
            ->where('package_id', '=', $request->package_id)
            ->where('package_limit_type_id', '=', PackageType::DROP_IN)
            ->where('box_id', '=', $tenant->getKey())
            ->where('is_active', '=', true)
            ->firstOrFail();

        $now = now();

        $existingUser = User::where('email', '=', $request->input('email'))->first();

        $existingUser?->update([
            ...$request->safe()->only([
                'name',
                'surname',
                'mobile',
                'date_of_birth',
                'gender_id',
            ]),
        ]);

        $leadMember = $this->leadService->createLeadUserMembership($request, $existingUser);

        $leadMember->update([
            'status' => LeadMemberStatus::DROP_IN,
            'type' => LeadMemberType::DROP_IN,
        ]);

        $tenantWaiver = $tenant->leadSettings?->waiver;

        // Create waiver for lead member
        if ($tenantWaiver instanceof LeadWaivers) {
            if ($tenantWaiver->isDigital()) {
                $waiver = LeadWaivers::create([
                    'user_id' => $leadMember->user_id, // Not sure if I should add this here?
                    'location_id' => $selectedLocation->getKey(),
                    'digital' => $tenantWaiver->isDigital(),
                    'digital_terms_and_conditions' => $tenantWaiver->digital_terms_and_conditions,
                    'parent_id' => $tenantWaiver->getKey(),
                    'signed_on' => $now,
                    'status' => WaiverStatus::SIGNED,
                    'ip_address' => $request->getClientIp(),
                ]);

                $leadMember->update(['waiver_id' => $waiver->getKey()]);
            }
        }

        // Create the lead to drop-in package record
        $userPackage = UserPackage::create([
            'user_id' => $leadMember->user_id,
            'package_id' => $dropInPackage->getKey(),
            'effective_date' => $now,
            'end_date' => $dropInPackage->getDefaultPeriodInterval(),
            'sessions_available' => 0,
            'notes' => $request->input('notes'),
        ]);

        // Generate invoice for lead member
        $invoice = (new InvoiceService())->generateInvoiceForLeadMember($leadMember, $dropInPackage->price, $now, $userPackage);

        $invoice->update(['discriminator' => InvoiceDiscriminator::DROP_IN_INVOICE]);

        // Get tenant drop-in-package settings
        $dropInPackageSettings = Arr::get($tenant->extra_parameters, 'dropInPackageSettings');

        if ($dropInPackageSettings['paymentType'] === 'adhoc') {
            $data = ['invoiceId' => $invoice->getKey()];

            $paymentGateway = (new PaymentGatewayService())->getCurrentSignUpSettingsForLocation($selectedLocation);

            if ($paymentGateway && $paymentGateway->has('locationPaymentGateway')) {
                $data['locationPaymentGatewayId'] = $paymentGateway->locationPaymentGateway->getKey();
            }

            return response()->json($data);
        } elseif ($dropInPackageSettings['paymentType'] === 'cash') {
            (new WidgetService())->completeDropIn($invoice, true);

            return response()->json([
                'public_token' => $selectedLocation->public_token ?? $tenant->public_token,
                'lead_token' => $leadMember->getKey(),
            ], 201);
        } else {
            abort(400, 'An error has occurred. Please contact '.$tenant->name.' for assistance.');
        }
    }

    public function getDropInPackages(GetDropInPackagesRequest $request)
    {
        $leadMember = LeadMember::findOrFail($request->input('lead_token'));

        $userPackages = UserPackage::query()
            ->select('user_to_package.*')
            ->addSelect(DB::raw('SUM(sessions_available) as sessions_available'))
            ->leftJoin('packages as p', 'p.package_id', '=', 'user_to_package.package_id')
            ->where('user_id', $leadMember->user_id)
            ->where('p.package_limit_type_id', '=', PackageType::DROP_IN->value)
            ->orderBy('user_to_package_id')
            ->orderBy('effective_date')
            ->orderByDesc('sessions_available')
            ->groupBy('user_to_package.package_id')
            ->active()
            ->get();

        return UserPackageResource::collection($userPackages);
    }

    public function createClassBooking(CreateClassBookingRequest $request)
    {
        $classDate = ClassDate::findOrFail($request->input('class_date_id'));
        $leadMember = LeadMember::findOrFail($request->input('lead_token'));
        $tenant = $classDate->class->tenant;
        $location = $classDate->class->location;
        $timezone = $location->timezone instanceof Timezone ? $location->timezone : $tenant->timezone;

        $classService = new ClassService();

        // Box booking threshold check
        $bookingThresholdDate = now(new DateTimeZone($timezone->zone));
        $bookingThresholdDate->addDays($tenant->booking_threshold);

        // Get classTime
        $startTime = $classService->getClassDateStartDateTime($classDate);
        $classDateTime = clone $classDate->date;
        $classDateTime->setTimezone(new DateTimeZone($timezone->zone));
        $classDateTime->setTime($startTime->format('H'), $startTime->format('i'), $startTime->format('s'));

        if ($classDateTime > $bookingThresholdDate) {
            abort(400, 'You cannot book this far in advance');
        }

        $canMemberBookOrErrorMessage = $classService->canPackageLeadMemberBookForClassDate(classDate: $classDate, isReturnErrorMessage: true, leadMember: $leadMember);

        if (is_string($canMemberBookOrErrorMessage)) {
            abort(400, $canMemberBookOrErrorMessage);
        }

        $classBooking = $classService->createClassBookingForClassDateAndLeadByUser($classDate, $leadMember);

        if (! $classBooking instanceof ClassBooking) {
            abort(400, 'Action could not be performed');
        }

        return response()->json(ClassBookingResource::make($classBooking), 201);
    }

    public function cancelClassBooking(CancelClassBookingRequest $request)
    {
        $classBooking = ClassBooking::findOrFail($request->input('class_booking_id'));
        $leadMember = LeadMember::findOrFail($request->input('lead_token'));

        if (! $classBooking->leadMember instanceof LeadMember) {
            abort(404, 'The class booking could not be found for this lead member.');
        }

        if ($classBooking->leadMember->getKey() !== $leadMember->getKey()) {
            abort(400, 'The class booking does not belong to this lead member.');
        }

        $cancelResult = (new ClassService())->cancelBookingForClassDateByUser($classBooking, $leadMember->user);

        if ($cancelResult !== true) {
            abort(400, 'Action could not be performed');
        }

        return ClassBookingResource::make($classBooking);
    }
}
