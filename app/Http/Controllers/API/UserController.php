<?php

namespace App\Http\Controllers\API;

use App\Enums\AccountType;
use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoiceStatus;
use App\Enums\LeadMemberStatus;
use App\Enums\PackageType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\WaiverStatus;
use App\Exports\UserExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\BulkChangePasswordRequest;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\DuplicateUserTenantsRequest;
use App\Http\Requests\User\ExportUsersRequest;
use App\Http\Requests\User\GetAccessTokenRequest;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Requests\User\MergeUserRequest;
use App\Http\Requests\User\ReadUserRequest;
use App\Http\Requests\User\SignUpRequest;
use App\Http\Requests\User\StoreDiscoveryUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\ValidateUserRequest;
use App\Http\Resources\TenantUserResource;
use App\Http\Resources\UserMinimalResource;
use App\Http\Resources\UserResource;
use App\Models\Bank;
use App\Models\LeadMember;
use App\Models\LinkedUser;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\Package;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserInvoice;
use App\Services\CrmService;
use App\Services\FinanceService;
use App\Services\InvoiceService;
use App\Services\PackageService;
use App\Services\PaymentGateways\GoCardlessService;
use App\Services\PaymentGateways\NetcashService;
use App\Services\PaymentGateways\PaymentGatewayService;
use App\Services\PaymentGateways\StripeConnectService;
use App\Services\TenantUserService;
use App\Services\UserContractService;
use App\Services\UserService;
use App\Services\UtilityService;
use App\Services\WidgetService;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Knuckles\Scribe\Attributes\QueryParam;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    #[QueryParam('filter[tenant_id]', 'integer', null, false)]
    #[QueryParam('filter[user_id]', 'integer', null, false)]
    #[QueryParam('filter[search]', 'string', null, false)]
    #[QueryParam('filter[is_redacted]', 'boolean', null, false)]
    #[QueryParam('filter[user_tenant_location_id]', 'integer', null, false)]
    #[QueryParam('filter[user_tenant_type_id]', 'integer', null, false)]
    #[QueryParam('filter[user_tenant_status_id]', 'integer', null, false)]
    #[QueryParam('filter[user_tenant_debit_status_id]', 'integer', null, false)]
    #[QueryParam('filter[user_tenant_programme_id]', 'integer', null, false)]
    #[QueryParam('filter[user_tenant_package_id]', 'integer', null, false)]
    #[QueryParam('filter[user_tenant_assigned_user_id]', 'integer', null, false)]
    #[QueryParam('filter[user_tenant_has_sessions_available_for_date]', 'date', null, false)]
    #[QueryParam('filter[lead_member_status]', 'integer', null, false)]
    #[QueryParam('filter[lead_member_type]', 'integer', null, false)]
    #[QueryParam('include', 'integer', null, false, 'health_provider,user_tenants')]
    #[QueryParam('page', 'integer', required: false)]
    #[QueryParam('per_page', 'integer', required: false)]
    public function list(ListUsersRequest $request)
    {
        $users = (new UserService())
            ->getUsersQueryBuilder()
            ->_paginate();

        if ($request->input('filter.is_minimal_data')) {
            return UserMinimalResource::collection($users);
        }

        $users->tap()
            ->transform(function ($user) use ($request) {
                if ($request->input('filter.tenant_id')) {

                    $user->userTenant->has_sessions_available_for_date = (bool) $user->has_sessions_available_for_date;

                    if (str($request->input('include'))->contains('isOverdue')) {
                        $user->userTenant->is_overdue = $user->userTenant->isMember() && (new FinanceService())->getAmountOutstanding($user->userTenant) > 0;
                    }

                    if (str($request->input('include'))->contains('userTenant.userPackages')) {
                        $user->userTenant->append('activeUserPackages');
                        // $user->userTenant->active_user_packages = $usersActivePackages->where('user_id', $user->getKey());
                    }

                    // if (! str($request->input('append'))->contains('sessions_available_for_date:')) {
                    $user->userTenant->unsetRelation('user');
                    $user->userTenant->unsetRelation('tenant');
                    // }

                    if ($user->userTenant->relationLoaded('userContract')) {
                        $user->userTenant->userContract?->unsetRelation('userTenant');
                    }
                }

                return $user;
            });

        return UserResource::collection($users);
    }

    public function export(ExportUsersRequest $request)
    {
        $filename = 'User-exports/users-export-'.Str::random(10).'.csv';

        Excel::store(new UserExport(), $filename, 'tmp');

        return response()->json(Storage::disk('tmp')->temporaryUrl($filename, now()->addMinute()));
    }

    public function show(ReadUserRequest $request, User $user): UserResource
    {
        if ($request->input('append') === 'tenantUser') {
            $user->load('tenantUser.tenant');
        }

        return new UserResource($user);
    }

    #[QueryParam('filter[email]', 'string', '', true)]
    #[QueryParam('filter[tenant_id]', 'integer', '', true)]
    public function validateUser(ValidateUserRequest $request): Response|bool
    {
        if (! auth()->check()) {
            auth()->shouldUse('web');

            $loginAttempt = auth()
                ->attempt(['email' => $request->input('email'), 'password' => $request->input('password')]);

            if (! $loginAttempt) {
                abort('401', 'Invalid email or password');
            }
        }

        $user = User::where('email', '=', $request->input('email'))
            ->firstOrFail();

        $staff = $user->tenantUser()
            ->where('box_id', '=', $request->input('tenant_id'))
            ->whereIn('user_type_id', UserType::staffUserTypeIds())->count();

        $member = $user->tenantUser()
            ->where('box_id', '=', $request->input('tenant_id'))
            ->where('user_type_id', '=', UserType::GYM_MEMBER->value)->count();

        $lead = $user->tenantUser()
            ->where('box_id', '=', $request->input('tenant_id'))
            ->where('user_type_id', '=', UserType::LEAD_MEMBER->value)->count();

        $data = [
            'id' => $user->getKey(),
            'name' => $user->name,
            'surname' => $user->surname,
            'email' => $user->email,
            'hasStaffUserTenant' => $staff > 0,
            'hasMemberUserTenant' => $member > 0,
            'hasLeadUserTenant' => $lead > 0,
        ];

        return response($data, 200);
    }

    public function store(CreateUserRequest $request): UserResource
    {
        $data = [
            ...$request->safe()->only([
                'name',
                'surname',
                'gender_id',
                'email',
                'id_number',
                'address',
                'health_provider_id',
                'date_of_birth',
                'mobile',
                'emergency_contact_name',
                'emergency_contact_mobile',
            ]),
            'user_type_id' => UserType::USER->value, //TODO: Remove this column sometime.
            'user_status_id' => UserStatus::ACTIVE->value,
        ];

        if ($request->has('password') && $request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $user = User::where('email', '=', $request->input('email'))
            ->withTrashed()
            ->first();

        if ($user) {
            $data['deleted'] = 0;
            $user->update($data);
            $user->refresh();
        } else {
            $user = User::create($data);
        }

        if ($request->has('image')) {
            $fileName = uniqid(rand(), true).'.'.$request->file('image')->getClientOriginalExtension();
            $filePath = 'profile-images/';

            $request->file('image')->storePubliclyAs($filePath, $fileName, ['disk' => 'public']);
            $user->update([
                'profilepic' => $filePath.$fileName,
            ]);
        }

        return new UserResource($user);
    }

    public function storeFromPartner(StoreDiscoveryUserRequest $request)
    {
        $user = User::query()->create([
            'name' => $request->safe()->collect()->get('name'),
            'surname' => $request->safe()->collect()->get('surname'),
            'id_number' => $request->safe()->collect()->get('id_number'),
            'date_of_birth' => $request->safe()->collect()->get('date_of_birth'),
            'email' => Str::uuid()->toString().'@octiv.localhost',
            'email_alt' => $request->safe()->collect()->get('email'),
            'health_provider_id' => $request->safe()->collect()->get('health_provider_id'),
            'is_redacted' => true,
            'user_type_id' => UserType::USER->value, //TODO: Remove this column sometime.
            'user_status_id' => UserStatus::ACTIVE->value,
            'terms_and_conditions_accepted' => 1,
            'terms_and_conditions_accepted_on' => now(),
        ]);

        $token = $user->createToken('discovery_vitality', ['discovery-vitality']);

        return (new UserResource($user))->additional([
            'access_token' => $token->accessToken,
            'access_token_expires_at' => $token->token->expires_at->toDateTimeString(),
        ]);
    }

    public function getAccessToken(GetAccessTokenRequest $request, User $user): UserResource
    {
        $user->tokens()->delete();

        $token = $user->createToken('discovery_vitality', ['discovery-vitality']);

        return (new UserResource($user))->additional([
            'access_token' => $token->accessToken,
            'access_token_expires_at' => $token->token->expires_at->toDateTimeString(),
        ]);
    }

    public function switch(User $user): array
    {
        if (! auth()->user()->isAdmin()) {

            if (! LinkedUser::query()
                ->where(function ($query) use ($user) {
                    $query->where('user_id', auth()->user()->getAuthIdentifier())
                        ->where('linked_user_id', $user->getAuthIdentifier());
                })->orWhere(function ($query) use ($user) {
                    $query->where('linked_user_id', auth()->user()->getAuthIdentifier())
                        ->where('user_id', $user->getAuthIdentifier());
                })->exists()
            ) {
                abort(404, 'You cannot switch to this account.');
            }
        }

        $token = $user->createToken(sprintf('impersonate-%s-%s', auth()->user()->email, now()->getTimestamp()));

        return [
            'token' => $token->accessToken,
            'expires_at' => $token->token->expires_at->toIso8601String(),
        ];
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        if ($request->has('image')) {

            if (is_null($request->image)) {
                $user->update([
                    'profilepic' => null,
                ]);
            } else {
                $fileName = uniqid(rand(), true).'.'.$request->file('image')->getClientOriginalExtension();
                $filePath = 'profile-images/';

                $request->file('image')->storePubliclyAs($filePath, $fileName, ['disk' => 'public']);

                $user->update([
                    'profilepic' => $filePath.$fileName,
                ]);
            }
        }

        $data = $request->safe()->except(['image', 'password']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        return new UserResource($user);
    }

    public function me()
    {
        /** @var User $user */
        $user = auth()->user();

        $user->loadMissing([
            'tenantUser.tenant.tenantCurrency',
            'tenantUser.tenant.memberCurrency',
            'tenantUser.tenant.region',
            'tenantUser.tenant.locations.healthProviders',
            'tenantUser.accessPrivileges.privilege',
            'tenantUser.locationAccessPrivileges',
            'tenantUser.locations',
            'healthProvider',
            'tenantUser.tenant.settings',
            'tenantUser.tenant.locations.locationPaymentGateways',
        ]);

        $user->tenantUser->transform(function (TenantUser $tenantUser) {
            return $tenantUser->setRelation(
                'locationAccessPrivileges',
                $tenantUser->locationAccessPrivileges->filter(fn ($lap) => $lap->location->tenant_id === $tenantUser->tenant_id)
            );
        });

        return new UserResource($user);
    }

    public function changePassword(BulkChangePasswordRequest $request)
    {
        if (auth()->user()->isAdmin()) {
            foreach ($request->get('user_ids') as $userId) {
                $user = User::find($userId);
                if ($user) {
                    (new UserService())->changePassword($user, $request->get('password'));
                }
            }
        } else {
            $user = auth()->user();

            // TODO: check existing password..

            (new UserService())->changePassword($user, $request->get('password'));

            $content = Markdown::parse(
                view('emails.password-changed', [
                    'memberName' => $user->name,
                    'memberSurname' => $user->surname,
                ])
            )->__toString();

            (new CrmService())->createScheduledEmail(
                content: $content,
                subject: 'Password Updated',
                to: $user->email,
            );
        }

        return response()->noContent();
    }

    public function signUp(SignUpRequest $request)
    {
        $paymentGatewayService = (new PaymentGatewayService());
        $user = null;
        $userTenant = null;
        $tenant = Tenant::findOrFail($request->input('tenant_id'));
        $package = Package::where('is_active', '=', true)->findOrFail($request->input('package_id'));
        $programme = Programme::findOrFail($request->input('programme_id'));
        $location = Location::findOrFail($request->input('location_id'));

        // Check if the package belongs to this box
        if ($package->tenant_id !== $tenant->tenant_id) {
            abort('400', 'This package does not belong to this facility.');
        }

        $isLimitedPackage = $package->type === PackageType::LIMITED;
        $locationPaymentGateway = $paymentGatewayService->getBoxFacilityPaymentGatewayBySettings($location, $isLimitedPackage);
        $existingUserQuery = User::where('email', '=', $request->input('email'));

        if ($existingUserQuery->count() > 1) {
            abort('400', 'Duplicate accounts detected. You will need to merge these by logging into Octiv first.');
        }

        $existingUser = $existingUserQuery->first();
        $deletedExistingUsers = User::where('email', '=', $request->input('email'))->onlyTrashed()->get();

        // Change the email of all existing users to prevent duplicates
        if ($deletedExistingUsers->count() > 0) {
            $utilityService = new UtilityService();

            foreach ($deletedExistingUsers as $deletedExistingUser) {
                $deletedExistingUser->update(['email' => $utilityService->setAliasEmail($deletedExistingUser->email)]);
            }
        }

        if ($request->has('user_id')) {
            $user = User::findOrFail($request->input('user_id'));

            $userTenant = (new TenantUserService)->getCurrentUserTenantForTenant($user, $tenant);

            if ($userTenant && $userTenant->type === UserType::LEAD_MEMBER) {
                $leadMember = LeadMember::query()
                    ->where('user_id', '=', $user->getKey())
                    ->where('box_facility_id', '=', $location->getKey())
                    ->latest()
                    ->first();

                $leadMember?->update([
                    'status' => LeadMemberStatus::CONVERTED,
                    'converted_on' => now(),
                ]);

            } else {
                $unpaidInvoice = UserInvoice::query()
                    ->select('finance_invoices.*')
                    ->join('user_to_facility', 'finance_invoices.user_to_facility_id', '=', 'user_to_facility.user_to_facility_id')
                    ->join('finance_invoice_items', 'finance_invoices.invoice_id', '=', 'finance_invoice_items.invoice_id')
                    ->where('user_to_facility.user_id', '=', $user->getKey())
                    ->where('finance_invoices.type', '=', 'invoice')
                    ->where('finance_invoices.discriminator', '=', InvoiceDiscriminator::SIGN_UP_INVOICE)
                    ->where('finance_invoices.status', '!=', InvoiceStatus::CREDITED)
                    ->where('finance_invoices.deleted', '=', false)
                    ->where('finance_invoice_items.deleted', '=', false)
                    ->latest()
                    ->first();

                if ($userTenant?->status === UserStatus::PENDING && $unpaidInvoice instanceof UserInvoice) {
                    return response()->json([
                        'invoiceId' => $unpaidInvoice->getKey(),
                        'locationPaymentGatewayId' => $locationPaymentGateway?->getKey(),
                    ]);
                } elseif ($userTenant instanceof TenantUser) {
                    abort(400, 'Email address already exists for this facility. In order to add a new package to your profile, please contact a facility administrator.');
                }
            }
        } elseif ($existingUser) {
            $existingUserTenant = (new TenantUserService)->getCurrentUserTenantForTenant($existingUser, $tenant);

            if ($existingUserTenant && $existingUserTenant->type === UserType::LEAD_MEMBER) {
                $leadMember = LeadMember::query()
                    ->where('user_id', '=', $existingUser->getKey())
                    ->where('box_facility_id', '=', $location->getKey())
                    ->latest()
                    ->first();

                $leadMember?->update([
                    'status' => LeadMemberStatus::CONVERTED,
                    'converted_on' => now(),
                ]);

                $existingUser->update([
                    ...$request->safe()->only([
                        'name',
                        'surname',
                        'gender_id',
                        'date_of_birth',
                        'email',
                        'mobile',
                        'id_number',
                        'address',
                        'emergency_contact_name',
                        'emergency_contact_mobile',
                    ]),
                    'password' => Hash::make($request->input('password')),
                ]);

            } elseif ($existingUserTenant) {
                abort(400, 'Email address already exists for this facility. In order to add a new package to your profile, please contact a facility administrator.');
            }

            $user = $existingUser;
            $userTenant = $existingUserTenant;
        }

        $selectedLocationPaymentGateway = $location->paymentGateway;

        // If limited package: set the user to no payment
        if ($isLimitedPackage && $locationPaymentGateway instanceof LocationPaymentGateway) {
            $paymentMethod = UserDebitStatus::NO_PAYMENT->value;
        } elseif ($isLimitedPackage && ! $locationPaymentGateway instanceof LocationPaymentGateway) {
            // Set to cash so that invoice can be generated for user
            $paymentMethod = UserDebitStatus::CASH->value;
        } else {
            $paymentMethod = $request->input('payment_details.debit_status_id');
        }

        $isPaymentMethodDebitOrder = (int) $paymentMethod === UserDebitStatus::DEBIT_ORDER->value;

        // Validate banking details if the user selected debit order for netcash
        if ($isPaymentMethodDebitOrder && in_array($selectedLocationPaymentGateway->getKey(), [PaymentGateway::SAGE_PAY_V2, PaymentGateway::SAGE_PAY_V3]) && (config('app.env') === 'production')) {
            $bank = Bank::find($request->input('payment_details.bank_id'));
            $bankBranchCode = $bank->universal_code ? $bank->universal_code : $request->input('payment_details.branch_code');

            // Validate the details
            $netcashValidationResult = (new NetcashService())->validateBankingDetails($location, $request->input('payment_details.account_number'), $bankBranchCode, AccountType::from($request->input('payment_details.account_type_id')));

            if (is_string($netcashValidationResult)) {
                abort(400, $netcashValidationResult);
            }
        }

        // Create user
        if (! $user) {
            $data = [
                ...$request->safe()->only([
                    'name',
                    'surname',
                    'gender_id',
                    'date_of_birth',
                    'email',
                    'mobile',
                    'id_number',
                    'address',
                    'emergency_contact_name',
                    'emergency_contact_mobile',
                ]),
                'user_type_id' => UserType::USER->value,
                'user_status_id' => UserStatus::ACTIVE->value,
            ];

            if ($request->has('password')) {
                $data['password'] = Hash::make($request->input('password'));
            }

            $user = User::create($data);
        }

        // Create user contract
        if ($tenant->signup_use_contract_and_waivers) {
            (new UserContractService())->createOrUpdateUserContract(null, $user, $location, null, $package->getDefaultPeriodIntervalDate(), null, true, $request->getClientIp());
            (new UserService())->createUserDigitalLeadWaiver($user, $location, WaiverStatus::SIGNED->value, $request->getClientIp());
        }

        $tenantService = (new TenantUserService());
        $shouldBeActive = $isPaymentMethodDebitOrder && in_array($selectedLocationPaymentGateway->getKey(), [PaymentGateway::NO_GATEWAY->value, PaymentGateway::SAGE_PAY_V3->value, PaymentGateway::THREE_PEAKS->value, PaymentGateway::GO_CARDLESS->value, PaymentGateway::SEPA->value]);
        $userDebitStatus = UserDebitStatus::tryFrom($paymentMethod);
        $userStatus = $shouldBeActive === true ? UserStatus::ACTIVE : UserStatus::PENDING;

        // Create or update user box membership(userToBox)
        if ($userTenant) {
            $userTenant->update([
                'type' => UserType::GYM_MEMBER,
                'user_status_id' => $userStatus,
                'programme_id' => $programme->getKey(),
                'user_debit_status_id' => $userDebitStatus,
            ]);

        } else {
            $userTenant = $tenantService->createUserBoxMembership($user, $tenant, UserType::GYM_MEMBER, [...$request->safe()->all()], null, null, $programme, $userDebitStatus, $userStatus);
        }

        if ($shouldBeActive) {
            $userTenant->update([
                'activated_on' => now(),
            ]);
        }

        $userLocation = (new TenantUserService)->getLocationUserByTenant($user, $tenant);

        if (! $userLocation) {
            // Create user location membership
            $tenantService->createUserFacilityMembership($user, $location);
        }

        // Create user package membership(userToPackage)
        $userPackage = (new PackageService())->createUserPackage($user, $package);

        // Set sessions for limited package
        if ($isLimitedPackage) {
            $userPackage->update(['sessions_available' => $package->limit]);
        }

        // Setup userPackage and generate invoice
        $invoice = (new InvoiceService())->createSignUpInvoice($userTenant, $userPackage, $location, $paymentMethod, $request->input('payment_details'));

        $data = [];
        $isAdhoc = $isLimitedPackage && ($paymentMethod == UserDebitStatus::CASH->value || $paymentMethod == UserDebitStatus::NO_PAYMENT->value);

        if ($isAdhoc) {
            if (! $invoice instanceof UserInvoice) {
                abort(400, 'Invoice could not be generated. Please contact your location for assistance.');
            }

            $data = [
                'invoice_id' => $invoice->getKey(),
                'location_payment_gateway_id' => $locationPaymentGateway?->getKey(),
            ];

            $invoice->update(['location_payment_gateway_id' => $locationPaymentGateway?->getKey()]);

            (new WidgetService())->sendPendingUserEmail($user, $location, $invoice->getKey(), $locationPaymentGateway?->getKey());
        } elseif (($location->payment_gateway_id === PaymentGateway::GO_CARDLESS->value) && $isPaymentMethodDebitOrder) {
            try {
                // Set member to pending in case they don't accept the mandate
                $userTenant->update(['user_status_id' => UserStatus::PENDING]);

                $data['link'] = (new GoCardlessService())->beginUserOnBoardingFlow($user, $location);
            } catch (Exception $e) {
                abort(400, $e->getMessage());
            }
        } elseif (($location->payment_gateway_id === PaymentGateway::SEPA->value) && $isPaymentMethodDebitOrder) {
            $data['link'] = config('octiv.web_app_url').'/sign/mandate';
        } elseif ($location->paymentGateway->isStripeConnect() && $isPaymentMethodDebitOrder) {
            try {
                // Set member to pending in case they don't accept the mandate
                $userTenant->update(['user_status_id' => UserStatus::PENDING]);

                $paymentMethods = (new StripeConnectService())->getDebitOrderPaymentMethodsForTenant($tenant);
                $data['link'] = (new StripeConnectService())->setupIntent($user, $paymentMethods, $location);
            } catch (Exception $e) {
                abort(400, $e->getMessage());
            }
        } elseif ($tenant->sign_up_redirect_url) {
            $data['redirect_url'] = $tenant->sign_up_redirect_url;
        }

        // Send login details if not adhoc. Email will be sent for adhoc when payment was successful.
        if (! $isAdhoc) {
            if ($userTenant->status === UserStatus::PENDING && $paymentMethod == UserDebitStatus::CASH->value && $invoice instanceof UserInvoice) {
                (new WidgetService())->sendPendingUserEmail($user, $location, $invoice->getKey());
            } else {
                (new CrmService())->scheduleWelcomeMessageMailer($user, $tenant, true, true);
            }
        }

        if ($isPaymentMethodDebitOrder && ! $invoice) {
            // Generate an invoice for a user
            $invoice = (new FinanceService())->generateInvoiceForUserPackage($user, $userPackage, $location, InvoiceDiscriminator::SIGN_UP_INVOICE->value);

            if ($invoice instanceof UserInvoice) {
                $adhocPaymentGatewayId = null;
                $adhocLocationPaymentGateways = (new PaymentGatewayService())->getAppropriateLocationPaymentGateways($tenant, $location, PaymentGatewayContext::AD_HOC, true);

                foreach ($adhocLocationPaymentGateways as $adhocLocationPaymentGateway) {
                    $adhocPaymentGatewayId = $adhocLocationPaymentGateway->getKey();
                    break;
                }

                // Send the coach and the member an email notifying them of what has happened.
                (new CrmService())->sendNoFutureBatchesNotifications($user, $location, $invoice, $adhocPaymentGatewayId);
            }
        }

        return response()->json($data, 201);
    }

    public function duplicateUserTenants(DuplicateUserTenantsRequest $request)
    {
        $users = User::select('user_id')
            ->withoutGlobalScopes()
            ->where('email', auth()->user()->email)
            ->get();

        $userTenants = TenantUser::query()
            ->withoutGlobalScopes()
            ->whereIn('user_id', $users->pluck('user_id')->toArray())
            ->where('user_id', '!=', $request->input('primary_user_id'))
            ->orderBy('user_id')
            ->orderBy('box_id')
            ->with(['user', 'tenant'])
            ->get();

        return TenantUserResource::collection($userTenants);
    }

    public function duplicateUsers()
    {
        return UserResource::collection(User::withoutGlobalScopes()
            ->where('email', '=', auth()->user()->email)->get());
    }

    public function merge(MergeUserRequest $request)
    {
        $shouldMerge = User::where('email', '=', auth()->user()->email)->count();

        if ($shouldMerge < 1) {
            abort('400', 'User has been merged already.');
        }

        $primaryUserId = $request->input('primary_user_id');
        $duplicateUserIds = $request->input('merge_user_ids');

        User::where('user_id', '=', $primaryUserId)
            ->withoutGlobalScopes()
            ->update(['deleted' => false]);
        // we do this check cause passwords might be different
        if (auth()->user()->getAuthIdentifier() != $primaryUserId) {
            User::withoutGlobalScopes()
                ->where('user_id', $primaryUserId)
                ->update(['password' => auth()->user()->password, 'deleted' => false]);
        }

        if ($request->has('merge_user_ids')) {
            foreach ($duplicateUserIds as $user) {
                $userDuplicate = $user;
                $userPrimary = $primaryUserId;

                $query = "SET @duplicateUserId := $userDuplicate;
                     SET @primaryUserId := $userPrimary;
                     UPDATE amendment_amendments SET ref_entity_id = @primaryUserId WHERE ref_entity_name LIKE '%\User' AND ref_entity_id = @duplicateUserId;
                     UPDATE amendment_amendments SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE attendance_records SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE body_measurement SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE body_measurement SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE body_weight SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE body_weight SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE box_settings SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE box_settings SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE classes SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE classes SET capturer_id = @primaryUserId WHERE capturer_id = @duplicateUserId;
                     UPDATE class_bookings SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE class_bookings SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE class_bookings SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE class_booking_waiting_list SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE class_booking_waiting_list SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE class_booking_waiting_list SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE class_coaches SET coach_id = @primaryUserId WHERE coach_id = @duplicateUserId;
                     UPDATE class_recurring_bookings SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE class_recurring_bookings SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE class_to_dates SET coach_id = @primaryUserId WHERE coach_id = @duplicateUserId;
                     UPDATE class_to_dates SET supporting_coach_id = @primaryUserId WHERE supporting_coach_id = @duplicateUserId;
                     UPDATE class_to_dates SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE coach_rates SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE coronavirus_vaccination_details SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE coronavirus_vaccination_details SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE coronavirus_vaccination_details SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE covid19_questionnaire_results SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE covid19_questionnaire_results SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE crm_mailers SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE crm_notification_unsubscriptions SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE crm_recipients SET ref_entity_id = @primaryUserId WHERE ref_entity_name = 'users' AND ref_entity_id = @duplicateUserId;
                     UPDATE drop_in_packages SET created_by = @primaryUserId WHERE created_by = @duplicateUserId;
                     UPDATE drop_in_packages SET updated_by = @primaryUserId WHERE updated_by = @duplicateUserId;
                     UPDATE drop_in_packages_to_lead_members SET created_by = @primaryUserId WHERE created_by = @duplicateUserId;
                     UPDATE drop_in_packages_to_lead_members SET updated_by = @primaryUserId WHERE updated_by = @duplicateUserId;
                     UPDATE facility_payment_gateway_settings SET modified_id = @primaryUserId WHERE modified_id = @duplicateUserId;
                     UPDATE finance_discounts SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE finance_facility_invoices SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE finance_facility_invoice_items SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE finance_facility_payments SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE finance_invoices SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE finance_invoices SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE finance_invoices SET upfront_user_id = @primaryUserId WHERE upfront_user_id = @duplicateUserId;
                     UPDATE finance_invoice_items SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE finance_payments SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE finance_top_ups SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE finance_top_ups SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE finance_user_gocardless_credentials SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE go_cardless_mandates SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE injury_injuries SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE injury_injuries SET created_for_id = @primaryUserId WHERE created_for_id = @duplicateUserId;
                     UPDATE injury_injuries SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE injury_updates SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE leaderboard SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE lead_members SET captured_by_id = @primaryUserId WHERE captured_by_id = @duplicateUserId;
                     UPDATE lead_members SET referred_by_id = @primaryUserId WHERE referred_by_id = @duplicateUserId;
                     UPDATE lead_waivers SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE mandates SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE own_benchmarks SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE own_benchmarks SET verifier_id = @primaryUserId WHERE verifier_id = @duplicateUserId;
                     UPDATE personal_wod SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE pos_sales SET purchaser_id = @primaryUserId WHERE purchaser_id = @duplicateUserId;
                     UPDATE pos_sales SET seller_id = @primaryUserId WHERE seller_id = @duplicateUserId;
                     UPDATE pos_stock_items SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE programmes SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE scheduled_push SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE scheduled_user_actions SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE special_rates SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE stripe_mandates SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE taggables SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE taggables SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE tags SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE tags SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE task_assignees SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE task_tasks SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE task_tasks SET updated_by_id = @primaryUserId WHERE updated_by_id = @duplicateUserId;
                     UPDATE users SET assigned_coach_id = @primaryUserId WHERE assigned_coach_id = @duplicateUserId;
                     UPDATE users SET linked_user_id = @primaryUserId WHERE linked_user_id = @duplicateUserId;
                     UPDATE users SET primary_user_account_id = @primaryUserId WHERE primary_user_account_id = @duplicateUserId;
                     UPDATE users_on_hold SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_to_box SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_access_privileges SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_banking_details SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_contracts SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_facility_access_privileges SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_login_attempts SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_to_batch SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_to_box SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_to_facility SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE user_to_package SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE wod_capture SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;
                     UPDATE wod_capture SET capturer_id = @primaryUserId WHERE capturer_id = @duplicateUserId;
                     UPDATE wod_capture SET verifier_id = @primaryUserId WHERE verifier_id = @duplicateUserId;
                     UPDATE wod_capture_exercises SET verifier_id = @primaryUserId WHERE verifier_id = @duplicateUserId;
                     UPDATE wod_capture_comments SET created_by_id = @primaryUserId WHERE created_by_id = @duplicateUserId;
                     UPDATE wod_capture_likes SET user_id = @primaryUserId WHERE user_id = @duplicateUserId;";

                DB::unprepared($query);
            }
            User::whereIn('user_id', $duplicateUserIds)->delete();
        }

        if ($request->has('update_users')) {
            foreach ($request->input('update_users') as $user) {
                User::where('user_id', $user['user_id'])
                    ->withoutGlobalScopes()
                    ->update([
                        'email' => $user['email'],
                        'password' => Hash::make('9ELF(3q6DU,g'),
                    ]);
            }
        }
    }
}
