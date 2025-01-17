<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\MandateType;
use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Resources\MandateResource;
use App\Models\Bank;
use App\Models\DebitDay;
use App\Models\FinanceDiscount;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\LocationAccessPrivilege;
use App\Models\LocationUser;
use App\Models\Mandate;
use App\Models\MandateGoCardless;
use App\Models\Package;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserAccessPrivilege;
use App\Models\UserBankingDetail;
use App\Models\UserInvoice;
use App\Models\UserInvoicePayment;
use App\Models\UserOnHold;
use App\Services\PaymentGateways\GoCardlessService;
use App\Services\PaymentGateways\StripeConnectService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TenantUserService
{
    public function queryByRequest($request = null)
    {
        return QueryBuilder::for(User::class, $request)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'tenantUser.box_id'),
                AllowedFilter::exact('programme_id', 'tenantUser.programme_id'),
                AllowedFilter::exact('assigned_coach_user_id', 'tenantUser.assigned_coach_utb_id'),

                AllowedFilter::exact('type_id', 'tenantUser.user_type_id'),
                AllowedFilter::exact('status_id', 'tenantUser.user_status_id'),
                AllowedFilter::exact('debit_status_id', 'tenantUser.user_debit_status_id'),

                AllowedFilter::exact('location_id', 'locations.box_facility_id'),
                AllowedFilter::exact('package_id', 'user.activePackages.package_id'),

                AllowedFilter::scope('start_date', 'activeAfter'),
                AllowedFilter::scope('end_date', 'endsAfter'),
                AllowedFilter::scope('birthday_between', 'birthdayBetween'),

                AllowedFilter::scope('search'),
                AllowedFilter::scope('has_sessions_remaining_for_date', 'hasSessionRemainingForDate'),
            ])->allowedSorts([
                'name', 'surname', 'email',
            ])->allowedIncludes(
                'tenantUser',
                'programme',
                'coach',
                'locations',
                'packages',
                'bankAccount',
                'scheduledActions',
            )->defaultSort('name');
    }

    public function deactivateBankAccount(TenantUser $tenantUser): void
    {
        $tenantUser->bankAccount()->update([
            'is_active' => false,
        ]);
    }

    public function deactivateUserCleanup(TenantUser $tenantUser): void
    {
        /** @var DebitBatchService $debitBatch */
        $debitBatch = resolve(DebitBatchService::class);

        /** @var ClassService $class */
        $class = resolve(ClassService::class);

        /** @var CrmService $crm */
        $crm = resolve(CrmService::class);

        // Deactivate all future debit batches for this user
        $debitBatch->deactivateFutureUserBatchesForUserBoxMembership($tenantUser);

        // Delete all future recurring class bookings.
        $class->removeClassRecurringBookingsAndClassBookingsForUser($tenantUser);
        $class->cancelFutureRecurringBookingsAfterUserScheduledDeactivation($tenantUser);

        // Cancel and/or delete all future class bookings
        $class->cancelFutureBookingsForUser($tenantUser, true);

        // Remove user from mailing lists
        $crm->removeUserFromAllMailingLists($tenantUser->user, $tenantUser->tenant);

        $crm->removeUserBoxMembershipRecipients($tenantUser);
    }

    public function updateStatus(TenantUser $tenantUser, UserStatus $status): void
    {
        $currentStatus = $tenantUser->status;

        // Only for gym members
        if ($tenantUser->type === UserType::GYM_MEMBER) {
            // Check if user is on-hold
            if ($currentStatus === UserStatus::ON_HOLD && $status !== UserStatus::ON_HOLD) {
                $onHoldUser = UserOnHold::query()
                    ->where('user_id', '=', $tenantUser->user_id)
                    ->where('box_id', '=', $tenantUser->box_id)
                    ->get();

                // Remove on-hold data
                if ($onHoldUser instanceof UserOnHold) {
                    if ($status === UserStatus::DEACTIVATED || $status === UserStatus::READY_FOR_TRANSFER) {
                        $onHoldUser->update(['is_on_hold' => false]);
                        $onHoldUser->delete();
                    } elseif ($status === UserStatus::ACTIVE) {
                        (new UserOnHoldService())->releaseOnHoldUser($onHoldUser, true);
                    }
                }
            }

            // Do the following if member is activated or re-activated
            if ($status === UserStatus::ACTIVE) {
                $crmService = resolve(CrmService::class);

                // Sign-up mailer
                if ($currentStatus == UserStatus::PENDING || $currentStatus == UserStatus::DEACTIVATED || $currentStatus == UserStatus::READY_FOR_TRANSFER) {
                    // Send member welcome mailer
                    $crmService->scheduleWelcomeMessageMailer($tenantUser->user, $tenantUser->tenant, false);
                }

                // Add member to mailing list that is for all active members
                $crmService->addUserToAllActiveMemberMailingLists($tenantUser->user, $tenantUser->tenant);

                // Set user's date activated
                $tenantUser->update(['activated_on' => now()]);

                $location = $this->getLocationUserByTenant($tenantUser->user, $tenantUser->tenant)?->location;

                if ($tenantUser->debit_status === UserDebitStatus::DEBIT_ORDER && $location instanceof Location && $tenantUser->bankAccount instanceof UserBankingDetail) {
                    $debitBatchService = resolve(DebitBatchService::class);

                    // Remove all future debit batches for user
                    $debitBatchService->deactivateFutureUserBatchesForUserBoxMembership($tenantUser);

                    // Add user to new facility debit batches
                    $debitBatchService->generateFutureDebitBatchesForUser($location, $tenantUser->bankAccount->debitDay, $tenantUser->user);
                }
            } elseif ($status === UserStatus::DEACTIVATED) {
                $tenantUser->update(['deactivated_on' => now()]);

                // Clean up. Remove debit batches, class bookings, mailers, etc
                $this->deactivateUserCleanUp($tenantUser);
            }
        }

        // Update user status
        $tenantUser->update(['status' => $status]);
    }

    public function store($data): TenantUser
    {
        if (! empty($data['user_status_id']) && $data['user_status_id'] == 2) {
            $data['activated_on'] = now();
        }
        $tenantUser = new TenantUser();
        $tenantUser->fill($data);
        $tenantUser->save();

        return $tenantUser;
    }

    public function updateGymMemberBoxMembership(TenantUser $tenantUser, array $data, Location $location)
    {
        $tenant = $location->tenant;

        // Get user's current boxFacility
        $user = $tenantUser->user;

        $usersCurrentBoxFacility = LocationUser::query()
            ->active()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereRelation('tenant', 'boxes.box_id', $tenantUser->tenant_id)
            ->first()
            ?->location;

        // Check if the user's facility has changed
        if (! $usersCurrentBoxFacility instanceof Location || ($usersCurrentBoxFacility->getKey() !== $location->getKey())) {
            // Disable current userFacilityMembership
            $this->disableUserFacilityMembership($user, $tenant);

            // Create user boxFacility membership(userToBoxFacility)
            $this->createUserFacilityMembership($user, $location);

            $tenantUser->update(['default_location_id' => $location->getKey()]);

            if ($tenantUser->isDebitOrder()) {
                // Remove all future debit batches for user
                (new DebitBatchService())->deactivateFutureUserBatchesForUserBoxMembership($tenantUser);

                // Add user to new facility debit batches
                (new DebitBatchService())->generateFutureDebitBatchesForUser(
                    $location,
                    $tenantUser->bankAccount->debitDay,
                    $user
                );
            }

            // Only remove future classes bookings and class recurring bookings only if limit inter facility bookings is enabled
            if ($tenant->limit_inter_facility_bookings) {
                (new ClassService())->removeClassRecurringBookingsAndClassBookingsForUser($tenantUser);
            }
        }

        // Update user box membership
        $this->updateUserBoxMembership($tenantUser, $data, Programme::findOrFail($data['programme_id']));

        return $user;
    }

    public function updateUserBoxMembership(TenantUser $tenantUser, array $data, ?Programme $programme = null): ?TenantUser
    {
        if (isset($data['assigned_user_id'])) {
            $assignedCoach = TenantUser::query()
                ->whereIn('user_type_id', UserType::tenantStaff())
                ->where('box_id', $tenantUser->tenant_id)
                ->where('user_id', $data['assigned_user_id'])
                ->where('end_date', '>', today())
                ->where('user_status_id', UserStatus::ACTIVE)
                ->first();

            if (! $assignedCoach) {
                abort('400', 'Assigned coach not found for this tenant_id '.$tenantUser->tenant_id);
            }
        }

        // Update the user box membership
        $tenantUser->fill([
            'member_id' => Arr::get($data, 'member_id', $tenantUser->member_id),
            'bio' => Arr::get($data, 'bio', $tenantUser->bio),
            'high_risk' => Arr::get($data, 'high_risk', $tenantUser->high_risk),
            'notes' => Arr::get($data, 'notes', $tenantUser->notes),
            'programme_id' => Arr::get($data, 'programme_id', $tenantUser->programme_id),
            'assigned_coach_utb_id' => $assignedCoach?->getKey() ?? $tenantUser->assigned_coach_id,
        ]);

        $tenantUser->save();

        return $tenantUser;
    }

    public function disableUserFacilityMembership(User $user, Tenant $tenant): void
    {
        LocationUser::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->whereRelation('tenant', 'boxes.box_id', $tenant->getKey())
            ->limit(1)
            ->update([
                'end_date' => today()->subDay(),
            ]);
    }

    public function getTenantUsersBy(int|Tenant $tenant, array $userTypes, int|Location|null $location = null, ?array $userStatuses = null): Collection
    {
        $builder = TenantUser::query()
            ->where('user_to_box.box_id', '=', $tenant instanceof Tenant ? $tenant->getKey() : $tenant)
            ->whereIn('user_to_box.user_type_id', $userTypes)
            ->with('user')
            ->withinActivePeriod();

        if ($location) {
            $builder->join('user_to_facility', 'user_to_box.user_id', 'user_to_facility.user_id')
                ->where('user_to_facility.box_facility_id', '=', $location instanceof Location ? $location->getKey() : $location)
                ->where('user_to_facility.end_date', '>', today()->toDateString());
        }

        if ($userStatuses) {
            $builder->whereIn('user_to_box.user_status_id', $userStatuses);
        }

        return $builder->get();
    }

    public function getCurrentUserTenantForTenant(string|int|User $user, string|int|Tenant $tenant): ?TenantUser
    {
        return TenantUser::query()
            ->where('user_id', '=', $user instanceof User ? $user->getKey() : $user)
            ->where('box_id', '=', $tenant instanceof Tenant ? $tenant->getKey() : $tenant)
            ->whereDate('end_date', '>', today())
            ->first();
    }

    public function getLocationUserByTenant(User|int $user, Tenant|int|null $tenant = null, Location|int|null $location = null): ?LocationUser
    {
        return LocationUser::query()
            ->join('box_facility', 'box_facility.box_facility_id', 'user_to_facility.box_facility_id')
            ->where('user_id', '=', $user instanceof User ? $user->getKey() : $user)
            ->where('box_facility.box_id', '=', $tenant instanceof Tenant ? $tenant->getKey() : $tenant)
            ->when($location, function ($query) use ($location) {
                $query->where('box_facility.box_facility_id', '=', $location instanceof Location ? $location->getKey() : $location);
            })
            ->where('end_date', '>', today()->toDateString())
            ->where('box_facility.is_active', '!=', false)
            ->first();
    }

    public function getLocationLeadByTenant(LeadMember|int $leadMember, Tenant|int|null $tenant = null): ?LocationUser
    {
        return LocationUser::query()
            ->join('box_facility', 'box_facility.box_facility_id', 'user_to_facility.box_facility_id')
            ->where('lead_member_id', '=', $leadMember instanceof LeadMember ? $leadMember->getKey() : $leadMember)
            ->where('box_facility.box_id', '=', $tenant instanceof Tenant ? $tenant->getKey() : $tenant)
            ->where('end_date', '>', today()->toDateString())
            ->where('box_facility.is_active', '!=', false)
            ->first();
    }

    public function getUserBoxMembershipsByTypesQueryBuilder(array $userTypesIds, ?Tenant $box = null, ?Location $boxFacility = null, ?UserStatus $userStatus = null, ?UserDebitStatus $userDebitStatus = null, ?string $packageIds = null, ?Programme $programme = null, ?TenantUser $assignedCoachUserBoxMembership = null, ?string $search = null, ?int $userBoxMembershipId = null)
    {
        return TenantUser::query()
            ->select('user_to_box.*')
            ->join('users', 'users.user_id', '=', 'user_to_box.user_id')
            ->where('user_to_box.end_date', '>', today()->toDateString())
            ->whereIn('user_to_box.user_type_id', $userTypesIds)
            ->when($box instanceof Tenant, function ($query) use ($box) {
                return $query->where('user_to_box.box_id', '=', $box->getKey());
            })
            ->when($boxFacility instanceof Location, function ($query) use ($boxFacility) {
                return $query->join('user_to_facility', 'users.user_id', '=', 'user_to_facility.user_id')
                    ->where('user_to_facility.box_facility_id', '=', $boxFacility->getKey())
                    ->where('user_to_facility.end_date', '>', today()->toDateString());
            })
            ->when(isset($userStatus), function ($query) use ($userStatus) {
                return $query->where('user_to_box.user_status_id', '=', $userStatus->value);
            })
            ->when(isset($userDebitStatus), function ($query) use ($userDebitStatus) {
                return $query->where('user_to_box.user_debit_status_id', '=', $userDebitStatus->value);
            })
            ->when(isset($packageIds), function ($query) use ($packageIds) {
                return $query->join('user_to_package', 'users.user_id', '=', 'user_to_package.user_id')
                    ->whereIn('user_to_package.package_id', $packageIds)
                    ->where('user_to_package.deleted', '=', false)
                    ->whereRaw('(user_to_package.end_Date IS NOT NULL AND now() BETWEEN user_to_package.effective_date AND user_to_package.end_date) OR (user_to_package.end_date IS NULL AND now() >= user_to_package.effective_date)');

            })
            ->when($programme instanceof Programme, function ($query) use ($programme) {
                return $query->where('user_to_box.programme_id', '=', $programme->getKey());
            })
            ->when($assignedCoachUserBoxMembership instanceof TenantUser, function ($query) use ($assignedCoachUserBoxMembership) {
                return $query->where('user_to_box.assigned_coach_utb_id', '=', $assignedCoachUserBoxMembership->getKey());
            })
            ->when(! empty($search), function ($query) use ($search) {
                return $query->where('users.name', 'LIKE', '%'.$search.'%');
            })
            ->when(isset($userBoxMembershipId), function ($query) use ($userBoxMembershipId) {
                return $query->where('user_to_box.user_box_membership_id', '=', $userBoxMembershipId);
            });
    }

    public function createGymMemberBoxMembership(User $user, UserType $userType, array $memberDetails, Tenant $tenant, Location $location): ?TenantUser
    {
        $paymentDetails = Arr::get($memberDetails, 'payment_details');
        $discountDetails = Arr::get($memberDetails, 'discount_details');
        $contractDetails = Arr::get($memberDetails, 'contract_details');

        $programme = Programme::query()->find(Arr::get($memberDetails, 'programme_id'));
        $package = Package::query()->find(Arr::get($memberDetails, 'package_id'));
        $tenantUser = $this->createUserBoxMembership($user, $tenant, $userType, $memberDetails, null, null, $programme, UserDebitStatus::from($paymentDetails['debit_status_id']));
        $userBoxFacilityMembership = $this->createUserFacilityMembership($user, $location);

        // Create user package membership(userToPackage)
        if ($package instanceof Package) {
            $userPackage = (new PackageService())->createUserPackage($user, $package);
        }

        // Create contract if one was requested
        if (is_array($contractDetails) && ! empty($contractDetails)) {
            (new UserContractService())->createOrUpdateUserContract(
                null,
                $user,
                $location,
                Carbon::parse($contractDetails['start_date']),
                isset($contractDetails['end_date']) ? Carbon::parse($contractDetails['end_date']) : null,
                array_key_exists('file', $contractDetails) ? $contractDetails['file'] : null
            );
        }

        // Create special rate or discount for user
        if (is_array($discountDetails) && ! empty($discountDetails)) {
            if ($discountDetails['discount_type'] === 'specialRate') {
                (new FinanceService())->createOrUpdateUserSpecialRate($tenantUser, $discountDetails['amount']);
            } else {
                $discount = FinanceDiscount::query()->find($discountDetails['discount_id']);

                if ($discount instanceof FinanceDiscount) {
                    (new FinanceService())->createFacilityMembershipDiscount($userBoxFacilityMembership, $discount);
                }
            }
        }

        // Cash member
        if ($tenantUser->debit_status === UserDebitStatus::CASH) {
            // Check if the auto invoicing date is set
            if ($paymentDetails['invoicing_type'] === 'custom_dates') {
                $tenantUser->update([
                    'auto_invoicing_day' => $paymentDetails['auto_invoicing_day'],
                    'auto_invoicing_due_day' => $paymentDetails['auto_invoicing_due_day'],
                ]);
            }
        }

        // Debit order member
        if ($tenantUser->debit_status === UserDebitStatus::DEBIT_ORDER) {
            $debitDay = DebitDay::query()->find($paymentDetails['debit_day_id']);
            $accountNumber = $paymentDetails['account_number'] ?? null;
            $accountHolderName = $paymentDetails['account_holder_name'] ?? null;
            $bank = isset($paymentDetails['bank_id']) ? Bank::query()->find($paymentDetails['bank_id']) : null;
            $accountType = isset($paymentDetails['account_type_id']) ? AccountType::from($paymentDetails['account_type_id']) : null;
            $waiverFile = array_key_exists('file', $paymentDetails) ? $paymentDetails['file'] : null;
            $branchCode = $paymentDetails['branch_code'] ?? null;
            $iban = $paymentDetails['iban'] ?? null;
            $bic = $paymentDetails['bic'] ?? null;
            $address = $paymentDetails['address'] ?? null;

            $userBankingDetails = $this->createOrUpdateUserBankingDetails($tenantUser, $debitDay, $accountNumber, $accountHolderName, $accountType, $bank, $branchCode, $iban, $bic, $address);

            // Upload waiver if one was sent
            if ($waiverFile) {
                $this->uploadUserBankingDetailsDocument($userBankingDetails, $waiverFile);
            }

            $proRateAmount = null;

            if (array_key_exists('pro_rated_amount', $paymentDetails) && $paymentDetails['pro_rated_amount'] > 0) {
                $proRateAmount = $paymentDetails['pro_rated_amount'];
            }

            // Add user to all future debit batches
            (new DebitBatchService())->generateFutureDebitBatchesForUser($location, $debitDay, $user, $proRateAmount);

            if ($location->paymentGateway->isGoCardless()) {
                (new MandateService())->sendOnboardingMail($tenantUser->user, $tenantUser->tenant);
            } elseif ($location->paymentGateway->isStripeConnect()) {
                (new StripeConnectService())->sendSetupIntentMail($tenantUser);
            }
        }

        // Upfront paying member
        if ($tenantUser->debit_status === UserDebitStatus::UP_FRONT_PAYMENT) {
            // Get payment period
            $upfrontPaymentStartDate = array_key_exists('upfront_payment_start_date', $paymentDetails) && $paymentDetails['upfront_payment_start_date'] != '' ? new \DateTime($paymentDetails['upfront_payment_start_date']) : new \DateTime();
            $upfrontPaymentEndDate = clone $upfrontPaymentStartDate;
            $upfrontPaymentEndDate->modify("+ {$paymentDetails['upfront_payment_period']} {$paymentDetails['upfront_payment_period_type']}");

            // Generate invoice for period and payment for invoice
            (new FinanceService())->generateUpfrontInvoiceAndPayment($tenantUser, $paymentDetails['upfront_payment_amount'], $upfrontPaymentStartDate, $upfrontPaymentEndDate, InvoicePaymentType::from($paymentDetails['upfront_payment_method']));
            // Set the upfront period date for member
            $tenantUser->update(['up_front_payment_end_date' => $upfrontPaymentEndDate]);
        }

        (new CrmService())->scheduleWelcomeMessageMailer($user, $tenant);

        return $tenantUser->loadMissing('assignedCoach');
    }

    public function createUserBoxMembership(User $user, Tenant $tenant, UserType $userType, array $memberDetails = [], ?Carbon $startingDate = null, ?Carbon $endingDate = null, ?Programme $programme = null, ?UserDebitStatus $userDebitStatus = null, ?UserStatus $userStatus = null): TenantUser
    {
        $userStatus = $userStatus ?? UserStatus::ACTIVE;
        $userDebitStatus = $userDebitStatus ?? UserDebitStatus::DEBIT_ORDER;
        $assignedCoach = null;

        if (isset($memberDetails['assigned_user_id'])) {
            $assignedCoach = TenantUser::query()
                ->whereIn('user_type_id', UserType::tenantStaff())
                ->where('box_id', $tenant->tenant_id)
                ->where('user_id', $memberDetails['assigned_user_id'])
                ->where('end_date', '>', today())
                ->where('user_status_id', UserStatus::ACTIVE)
                ->first();

            if (! $assignedCoach) {
                abort('400', 'Assigned coach not found for this tenant_id '.$tenant->tenant_id);
            }
        }

        // Link user to box
        $tenantUser = TenantUser::query()->create([
            'box_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'effective_date' => $startingDate ?? today(),
            'end_date' => $endingDate ?? Carbon::parse('2025-12-31'),
            'user_type_id' => $userType->value,
            'member_id' => isset($memberDetails['member_id']) ? trim($memberDetails['member_id']) : null,
            'bio' => isset($memberDetails['bio']) ? trim($memberDetails['bio']) : null,
            'user_status_id' => $userStatus->value,
            'user_debit_status_id' => $userDebitStatus->value,
            'assigned_coach_utb_id' => $assignedCoach?->getKey() ?? null,
            'notes' => $memberDetails['notes'] ?? null,
            'deleted' => $memberDetails['deleted'] ?? 0,
        ]);

        // Set activated only if status id active
        if ($userStatus === UserStatus::ACTIVE) {
            $tenantUser->update(['activated_on' => now()]);
        }

        if ($programme instanceof Programme) {
            $tenantUser->update(['programme_id' => $programme->getKey()]);
        }

        return $tenantUser;
    }

    public function createUserFacilityMembership(User|LeadMember $userOrLeadMember, Location $boxFacility, ?\DateTime $startingDate = null, ?\DateTime $endingDate = null): Builder|LocationUser
    {
        if ($startingDate === null) {
            $startingDate = now()->format('Y-m-d');
        }

        if ($endingDate === null) {
            $endingDate = now()->addYear()->format('Y-m-d');
        }

        // Link user to location
        return LocationUser::query()->create([
            'box_facility_id' => $boxFacility->getKey(),
            'effective_date' => $startingDate,
            'end_date' => $endingDate,
            'user_id' => $userOrLeadMember instanceof User ? $userOrLeadMember->getKey() : null,
            'lead_member_id' => $userOrLeadMember instanceof LeadMember ? $userOrLeadMember->getKey() : null,
        ]);
    }

    public function createStaffMemberBoxMembership(User $user, UserType $userType, $memberDetails, Tenant $tenant, ?Location $boxFacility = null): ?TenantUser
    {
        // Create staff member box membership
        $staffMemberBoxMembership = $this->createUserBoxMembership($user, $tenant, $userType, $memberDetails);

        // If this user is a location admin or location check-in user create link for boxFacility
        if (in_array($userType, [UserType::BOX_FACILITY_ADMIN, UserType::LOCATION_CHECK_IN]) && $boxFacility instanceof Location) {
            $this->createUserFacilityMembership($user, $boxFacility);
        }

        // Schedule welcome emails for user
        (new CrmService)->scheduleWelcomeMessageMailer(
            user: $user,
            tenant: $tenant
        );

        // Create user/facility privileges for the new user
        (new AccessPrivilegeService)->initializeUserPrivileges($staffMemberBoxMembership, $staffMemberBoxMembership->type);
        (new AccessPrivilegeService)->initializeUserFacilityPrivileges($staffMemberBoxMembership);

        return $staffMemberBoxMembership;
    }

    public function createOrUpdateUserBankingDetails(TenantUser $userBoxMembership, DebitDay $debitDay, ?string $accountNumber = null, ?string $accountName = null, $accountType = null, ?Bank $bank = null, ?string $branchCode = null, ?string $iban = null, ?string $bic = null, ?string $address = null): object
    {
        $existingUserBankingDetails = UserBankingDetail::query()
            ->where('user_id', $userBoxMembership->user_id)
            ->where('box_id', $userBoxMembership->box_id)
            ->where('is_active', true)
            ->first();

        if ($existingUserBankingDetails instanceof UserBankingDetail) {
            $existingUserBankingDetails->setAttribute('is_active', false);
            $existingUserBankingDetails->save();
        }

        $newBankingDetails = UserBankingDetail::query()->create([
            'user_id' => $userBoxMembership->user_id,
            'box_id' => $userBoxMembership->box_id,
            'debit_day_id' => $debitDay->getKey(),
            'account_name' => $accountName,
            'account_no' => $accountNumber,
            'account_type' => $accountType,
            'bank_id' => $bank?->getKey(),
            'branch_code' => $branchCode,
            'iban' => $iban ? Str::replace(' ', '', $iban) : null,
            'bic' => $bic ? Str::replace(' ', '', $bic) : null,
            'address' => $address,
        ]);

        // Set the user's payment type to Debit order if they are not
        if ($userBoxMembership->user_debit_status_id !== UserDebitStatus::DEBIT_ORDER) {
            $userBoxMembership->update([
                'user_debit_status_id' => UserDebitStatus::DEBIT_ORDER,
            ]);
        }

        return $newBankingDetails;
    }

    public function uploadUserBankingDetailsDocument(UserBankingDetail $userBankingDetails, $waiverFile): void
    {
        Storage::disk('public')->put('payment-details-waiver/'.$userBankingDetails->user_id, $waiverFile);
        $userBankingDetails->update(['waiver' => $waiverFile->getClientOriginalName()]);
    }

    public function updateStaffMemberBoxMembership(TenantUser $staffMember, array $data): TenantUser
    {
        $authTenantUser = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $staffMember->tenant);

        $box = $staffMember->tenant;
        $user = $staffMember->user;

        // Get current and new user type
        $oldUserType = $staffMember->type;
        $userType = isset($data['type_id']) ? UserType::from($data['type_id']) : $staffMember->type;

        // Update user details
        $this->updateUserBoxMembership($staffMember, $data);

        // Only super admin, head coach and box admin can edit access privileges
        if (auth()->user()->isAdmin() || $authTenantUser->isHeadCoach() || $authTenantUser->isTenantAdmin()) {

            // Check if the user type has changed
            if ($oldUserType != $userType) {

                // Get (delete) all accessPrivileges for user
                UserAccessPrivilege::query()
                    ->where('user_id', $staffMember->user_id)
                    ->where('box_id', $staffMember->tenant_id)
                    ->delete();

                // Check if user's current user type is location admin or location check-in
                if (in_array($oldUserType, [UserType::BOX_FACILITY_ADMIN, UserType::LOCATION_CHECK_IN])) {
                    // DISABLE ACTIVE OLD FACILITY
                    $this->disableUserFacilityMembership($user, $box);

                    // SET THE USERS DEFAULT DASHBOARD FACILITY
                    $staffMember->default_location_id = null;
                    $staffMember->save();
                }

                // If this user is a location admin or location check-in user update link for boxFacility
                if (Arr::has($data, 'location_id') && in_array($userType, [UserType::BOX_FACILITY_ADMIN, UserType::LOCATION_CHECK_IN])) {
                    $newLocation = Location::findOrFail($data['location_id']);

                    // INSET NEW FACILITY LINK
                    $this->createUserFacilityMembership($user, $newLocation);

                    // SET THE USERS DEFAULT DASHBOARD FACILITY
                    $staffMember->default_location_id = $newLocation->getKey();
                    $staffMember->save();
                }

                // Update the user type
                $staffMember->type = $userType;
                $staffMember->save();

                // Create privileges for new user type
                (new AccessPrivilegeService())->initializeUserPrivileges($staffMember, $userType);
            } else {
                // Update the user's access privileges

                // Get all active accessPrivileges for user
                $activeAccessPrivilegesForUser = UserAccessPrivilege::query()
                    ->where('user_id', $staffMember->user_id)
                    ->where('box_id', $staffMember->tenant_id)
                    ->where('revoked', false)
                    ->whereNull('revoked_on')
                    ->get();

                $currentAccessPrivilegesIds = $activeAccessPrivilegesForUser->pluck('access_privilege_id')->toArray();

                $selectedAccessPrivilegesIds = $data['user_access_privileges'] ?? [];

                // Get new privileges and privileges that have to be revoked
                $newAccessPrivileges = array_diff($selectedAccessPrivilegesIds, $currentAccessPrivilegesIds);
                $revokedAccessPrivileges = array_diff($currentAccessPrivilegesIds, $selectedAccessPrivilegesIds);

                // If this user is a location admin or location check-in user update link for boxFacility
                if (Arr::has($data, 'location_id') && in_array($userType, [UserType::BOX_FACILITY_ADMIN, UserType::LOCATION_CHECK_IN])) {
                    $oldBoxFacility = LocationUser::query()
                        ->active()
                        ->where('user_id', $staffMember->user_id)
                        ->whereRelation('location', 'box_id', $staffMember->tenant_id)
                        ->first()
                        ?->location;

                    $newBoxFacility = Location::findOrFail($data['location_id']);

                    if ($oldBoxFacility->getKey() !== $newBoxFacility->getKey()) {
                        // DISABLE ACTIVE OLD FACILITY
                        $this->disableUserFacilityMembership($user, $box);

                        // INSET NEW FACILITY LINK
                        $this->createUserFacilityMembership($user, $newBoxFacility);

                        // SET THE USERS DEFAULT DASHBOARD FACILITY
                        $staffMember->default_location_id = $newBoxFacility->getKey();
                        $staffMember->save();
                    }
                }

                // Create the new access privileges
                if (count($newAccessPrivileges) > 0) {
                    foreach ($newAccessPrivileges as $accessPrivilegeId) {

                        $userAccessPrivilege = UserAccessPrivilege::query()
                            ->where('user_id', $staffMember->user_id)
                            ->where('box_id', $staffMember->tenant_id)
                            ->where('access_privilege_id', $accessPrivilegeId)
                            ->first();

                        if ($userAccessPrivilege instanceof UserAccessPrivilege) {
                            $userAccessPrivilege->update([
                                'revoked' => false,
                                'revoked_on' => null,
                            ]);
                        } else {
                            UserAccessPrivilege::create([
                                'user_id' => $staffMember->user_id,
                                'box_id' => $staffMember->tenant_id,
                                'access_privilege_id' => $accessPrivilegeId,
                                'revoked' => false,
                                'revoked_on' => null,
                            ]);
                        }
                    }
                }

                // Privileges that have to be revoked
                if (count($revokedAccessPrivileges) > 0) {
                    foreach ($revokedAccessPrivileges as $accessPrivilegeId) {

                        $userAccessPrivilege = UserAccessPrivilege::query()
                            ->where('user_id', $staffMember->user_id)
                            ->where('box_id', $staffMember->tenant_id)
                            ->where('access_privilege_id', $accessPrivilegeId)
                            ->first();

                        if ($userAccessPrivilege instanceof UserAccessPrivilege) {
                            $userAccessPrivilege->update([
                                'revoked' => true,
                                'revoked_on' => now(),
                            ]);
                        } else {
                            UserAccessPrivilege::create([
                                'user_id' => $staffMember->user_id,
                                'box_id' => $staffMember->tenant_id,
                                'access_privilege_id' => $accessPrivilegeId,
                                'revoked' => true,
                                'revoked_on' => now(),
                            ]);
                        }
                    }
                }
            }

            // Update boxFacility access
            if ((! in_array($userType, [UserType::BOX_FACILITY_ADMIN, UserType::LOCATION_CHECK_IN]))
                && array_key_exists('location_access_privileges', $data)
            ) {

                // Get active user facility access privileges
                $userFacilityAccessPrivileges = LocationAccessPrivilege::query()
                    ->where('user_id', $staffMember->user_id)
                    ->where('revoked', false)
                    ->whereNull('revoked_on')
                    ->whereRelation('location', 'box_id', $staffMember->tenant_id)
                    ->get();

                $selectedFacilityAccessPrivilegesIds = $data['location_access_privileges'];
                $currentFacilityAccessPrivilegesIds = $userFacilityAccessPrivileges->pluck('box_facility_id')->toArray();

                // Get new facility privileges and facility privileges that have to be revoked
                $newAccessPrivilegesFacilities = array_diff($selectedFacilityAccessPrivilegesIds, $currentFacilityAccessPrivilegesIds);
                $revokedAccessPrivilegesFacilities = array_diff($currentFacilityAccessPrivilegesIds, $selectedFacilityAccessPrivilegesIds);

                // Create the new
                if (count($newAccessPrivilegesFacilities) > 0) {
                    foreach ($newAccessPrivilegesFacilities as $boxFacilityId) {

                        $location = Location::findOrFail($boxFacilityId);

                        $userFacilityAccessPrivilege = LocationAccessPrivilege::query()
                            ->where('user_id', $staffMember->user_id)
                            ->where('box_facility_id', $location->getKey())
                            ->first();

                        if (! $userFacilityAccessPrivilege instanceof LocationAccessPrivilege) {
                            $userFacilityAccessPrivilege = LocationAccessPrivilege::create([
                                'user_id' => $staffMember->user_id,
                                'box_facility_id' => $location->getKey(),
                                'revoked' => false,
                                'revoked_on' => null,
                            ]);
                        }

                        $userFacilityAccessPrivilege->update([
                            'revoked' => false,
                            'revoked_on' => null,
                        ]);
                    }
                }

                // Privileges that have to be revoked
                if (count($revokedAccessPrivilegesFacilities) > 0) {
                    foreach ($revokedAccessPrivilegesFacilities as $boxFacilityId) {

                        $userFacilityAccessPrivilege = LocationAccessPrivilege::query()
                            ->where('user_id', $staffMember->user_id)
                            ->where('box_facility_id', $boxFacilityId)
                            ->first();

                        if ($userFacilityAccessPrivilege instanceof LocationAccessPrivilege) {
                            $userFacilityAccessPrivilege->update([
                                'revoked' => true,
                                'revoked_on' => now(),
                            ]);
                        }
                    }
                }
            }
        }

        return $staffMember;
    }

    public function getFinanceDetailsForTenantUser(TenantUser $tenantUser): array
    {
        // Get finance details
        $financeDetails = [
            'outstanding_amount' => null,
            'last_paid_invoice' => null,
        ];

        // Get amount outstanding for all invoices
        $outstandingAmount = (new FinanceService())->getAmountOutstanding($tenantUser);

        if ($outstandingAmount > 0) {
            $financeDetails['outstanding_amount'] = $outstandingAmount;
        }

        // Get last paid invoice for member
        $paidInvoices = (new InvoiceService())->getInvoicesForUser($tenantUser->user, $tenantUser->tenant, null, null, null, InvoiceStatus::PAID, 'DESC');

        if (count($paidInvoices) > 0) {
            /** @var UserInvoice $lastPaidInvoice */
            $lastPaidInvoice = $paidInvoices[0];
            $lastPayment = $lastPaidInvoice->payments()->latest()->first();

            if ($lastPayment instanceof UserInvoicePayment) {
                $financeDetails['last_paid_invoice'] = [
                    'id' => $lastPayment->getKey(),
                    'date_time' => $lastPayment->date_time,
                    'type' => $lastPayment->type,
                ];
            }
        }

        // User's mandate information
        $locationUser = $this->getLocationUserByTenant($tenantUser->user, $tenantUser->tenant);

        $isGoCardless = $locationUser?->location instanceof Location && $locationUser->location->payment_gateway_id === PaymentGateway::GO_CARDLESS->value;
        $isSepa = $locationUser?->location instanceof Location && $locationUser->location->payment_gateway_id === PaymentGateway::SEPA->value;

        if ($isGoCardless || $isSepa) {
            $financeDetails['mandate'] = null;

            if ($isGoCardless) {
                $sentAt = $tenantUser->go_cardless_link_sent_on ? $tenantUser->go_cardless_link_sent_on->format('Y-m-d H:i:s') : null;

                $mandate = (new GoCardlessService())->getMandateForUser($tenantUser->user, $locationUser->location)->first();

                if ($mandate instanceof MandateGoCardless) {
                    $financeDetails['mandate'] = [
                        'id' => $mandate->getKey(),
                        'status' => $mandate->status->value,
                        'sentAt' => $sentAt,
                    ];
                } elseif ($sentAt) {
                    $financeDetails['mandate'] = [
                        'sentAt' => $sentAt,
                    ];
                }
            } else {
                $latestMandate = (new MandateService())->getLatestMandate($tenantUser->user_id, $tenantUser->tenant_id, MandateType::SEPA);

                if ($latestMandate instanceof Mandate) {
                    $financeDetails['mandate'] = new MandateResource($latestMandate);
                }
            }
        }

        return $financeDetails;
    }
}
