<?php

namespace App\Http\Controllers\API;

use App\Enums\AccountType;
use App\Enums\FinancePaymentTokenType;
use App\Enums\InvoicePaymentType;
use App\Enums\MandateStatus;
use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendWelcomeEmailRequest;
use App\Http\Requests\TenantUser\ApproveAllPendingTenantUserRequest;
use App\Http\Requests\TenantUser\BulkDeleteTenantUserRequest;
use App\Http\Requests\TenantUser\DeleteTenantUserRequest;
use App\Http\Requests\TenantUser\GetPaymentDetailsRequest;
use App\Http\Requests\TenantUser\ListUserTenantsContractsAndWaiversRequest;
use App\Http\Requests\TenantUser\ListUserTenantsFinancesRequest;
use App\Http\Requests\TenantUser\RenewUpFrontPaymentRequest;
use App\Http\Requests\TenantUser\ShowTenantUserRequest;
use App\Http\Requests\TenantUser\StoreUserTenantRequest;
use App\Http\Requests\TenantUser\UpdateDebitStatusRequest;
use App\Http\Requests\TenantUser\UpdateHighRiskRequest;
use App\Http\Requests\TenantUser\UpdateProgrammeRequest;
use App\Http\Requests\TenantUser\UpdateStatusRequest;
use App\Http\Requests\TenantUser\UpdateTenantUserPaymentDetailsRequest;
use App\Http\Requests\TenantUser\UpdateUserTenantRequest;
use App\Http\Resources\TenantUserResource;
use App\Jobs\SendWelcomeEmail;
use App\Models\Bank;
use App\Models\DebitDay;
use App\Models\FinanceDiscount;
use App\Models\FinancePaymentToken;
use App\Models\Location;
use App\Models\LocationUserDiscount;
use App\Models\MandateGoCardless;
use App\Models\Programme;
use App\Models\SpecialRate;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserBankingDetail;
use App\Models\UserInvoice;
use App\Models\UserOnHold;
use App\Services\AccessPrivilegeService;
use App\Services\CrmService;
use App\Services\DebitBatchService;
use App\Services\FinanceService;
use App\Services\InvoiceService;
use App\Services\LocationDiscountService;
use App\Services\MandateService;
use App\Services\PaymentGateways\NetcashService;
use App\Services\PaymentGateways\StripeConnectService;
use App\Services\PaymentTokenService;
use App\Services\ProgrammeService;
use App\Services\TenantUserService;
use App\Services\UserPackageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class UserTenantController extends Controller
{
    #[QueryParam('filter[tenant_id]', 'integer', null, true)]
    #[QueryParam('filter[location_id]', 'integer', null, false)]
    #[QueryParam('filter[type_id]', 'integer', null, false)]
    #[QueryParam('filter[status_id]', 'integer', null, false)]
    #[QueryParam('filter[programme_id]', 'integer', null, false)]
    #[QueryParam('filter[debit_status_id]', 'integer', null, false)]
    #[QueryParam('filter[user_id]', 'integer', null, false)]
    #[QueryParam('filter[search]', 'string', null, false)]
    #[QueryParam('page', 'integer', required: false)]
    #[QueryParam('per_page', 'integer', required: false)]
    public function finances(ListUserTenantsFinancesRequest $request)
    {
        $userTenants = QueryBuilder::for(TenantUser::class)
            ->select('user_to_box.*')
            ->join('users', 'user_to_box.user_id', '=', 'users.user_id')
            ->allowedIncludes([
                'userContract',
                AllowedInclude::callback('isOverdue', function ($query) {
                }, 'user'),
            ])
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'user_to_box.box_id'),
                AllowedFilter::callback('location_id', function (Builder $query, $value) {
                    $query->whereHas('locations', function ($query) use ($value) {
                        $query->where('user_to_facility.box_facility_id', '=', $value);
                    });
                }),
                AllowedFilter::exact('type_id', 'user_type_id'),
                AllowedFilter::exact('status_id', 'user_status_id'),
                AllowedFilter::exact('programme_id'),
                AllowedFilter::exact('debit_status_id', 'user_debit_status_id'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::scope('search'),
            ])
            ->allowedSorts([
                AllowedSort::field('name', 'users.name'),
                AllowedSort::field('surname', 'users.surname'),
                AllowedSort::field('upfront_payment_end_date', 'up_front_payment_end_date'),
                AllowedSort::field('payment_type', 'user_debit_status_id'),
            ])
            ->with('user')
            ->_paginate()
            ->tap()
            ->transform(function ($userTenant) use ($request) {
                $userTenant->append(['totalAmount', 'activeUserPackages']);

                if (str($request->input('include'))->contains('isOverdue')) {
                    $userTenant->is_overdue = $userTenant->isMember() && (new FinanceService())->getAmountOutstanding($userTenant) > 0;
                }

                if ($userTenant->relationLoaded('userContract')) {
                    $userTenant->userContract->unsetRelation('userTenant');
                }

                return $userTenant;

            });

        return TenantUserResource::collection($userTenants);
    }

    #[QueryParam('filter[tenant_id]', 'integer', null, true)]
    #[QueryParam('filter[location_id]', 'integer', null, false)]
    #[QueryParam('filter[type_id]', 'integer', null, false)]
    #[QueryParam('filter[status_id]', 'integer', null, false)]
    #[QueryParam('filter[date_to_query]', 'integer', null, false)]
    #[QueryParam('filter[start_date]', 'integer', null, false)]
    #[QueryParam('filter[end_date]', 'integer', null, false)]
    #[QueryParam('filter[user_id]', 'integer', null, false)]
    #[QueryParam('filter[search]', 'string', null, false)]
    #[QueryParam('page', 'integer', required: false)]
    #[QueryParam('per_page', 'integer', required: false)]
    public function contractsAndWaivers(ListUserTenantsContractsAndWaiversRequest $request)
    {
        $userTenants = QueryBuilder::for(TenantUser::class)
            ->select('user_to_box.*')
            ->join('users', 'user_to_box.user_id', '=', 'users.user_id')
            ->allowedIncludes([
                'userContract',
                AllowedInclude::callback('isOverdue', function ($query) {
                }, 'user'),
            ])
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'user_to_box.box_id'),
                AllowedFilter::callback('location_id', function (Builder $query, $value) {
                    $query->whereHas('locations', function ($query) use ($value) {
                        $query->where('user_to_facility.box_facility_id', '=', $value);
                    });
                }),
                AllowedFilter::exact('type_id', 'user_type_id'),
                AllowedFilter::exact('status_id', 'user_status_id'),
                AllowedFilter::callback('date_to_query', function (Builder $query, $dateToQuery) {
                    $query->when($dateToQuery !== 'created_on', function (Builder $query) {
                        $query->leftJoin('user_contracts', function ($join) {
                            $join->on('user_to_box.user_id', '=', 'user_contracts.user_id')
                                ->on('user_to_box.box_id', '=', 'user_contracts.box_id');
                        });
                    });

                    $startDate = request()->input('filter.start_date');
                    $endDate = request()->input('filter.end_date');

                    $dateTypes = [
                        'starting_on' => 'user_contracts.starting_on',
                        'ending_on' => 'user_contracts.starting_on',
                        'created_on' => 'user_to_box.created_on',
                    ];

                    if ($startDate && $endDate) {
                        $query->whereBetween($dateTypes[$dateToQuery], [$startDate, $endDate]);
                    } elseif ($dateToQuery && $startDate) {
                        $query->where($dateTypes[$dateToQuery], '>=', $startDate);
                    } elseif ($dateToQuery && $endDate) {
                        $query->where($dateTypes[$dateToQuery], '<=', $endDate);
                    }
                }),
                AllowedFilter::exact('user_id'),
                AllowedFilter::scope('search'),
            ])
            ->allowedSorts([
                AllowedSort::field('name', 'users.name'),
                AllowedSort::field('surname', 'users.surname'),
                AllowedSort::callback('contract_starting_at', function (Builder $query, $descending) {
                    $query->leftJoin('user_contracts', function ($join) {
                        $join->on('user_contracts.user_id', '=', 'users.user_id')
                            ->where('user_contracts.box_id', '=', request()->input('filter.tenant_id'));
                    })->orderBy('user_contracts.starting_on', $descending ? 'DESC' : 'ASC');
                }),
                AllowedSort::callback('contract_ending_at', function (Builder $query, $descending) {
                    $query->leftJoin('user_contracts', function ($join) {
                        $join->on('user_contracts.user_id', '=', 'users.user_id')
                            ->where('user_contracts.box_id', '=', request()->input('filter.tenant_id'));
                    })->orderBy('user_contracts.ending_on', $descending ? 'DESC' : 'ASC');
                }),
            ])
            ->has('user')
            ->with(['user', 'userContract'])
            ->groupBy('user_to_box.user_to_box_id')
            ->_paginate()
            ->tap()
            ->transform(function ($userTenant) use ($request) {
                $userTenant->append(['waiver', 'activeUserPackages']);

                if (str($request->input('include'))->contains('isOverdue')) {
                    $userTenant->is_overdue = $userTenant->isMember() && (new FinanceService())->getAmountOutstanding($userTenant) > 0;
                }

                if ($userTenant->relationLoaded('userContract')) {
                    $userTenant->userContract?->unsetRelation('userTenant');
                }

                return $userTenant;

            });

        return TenantUserResource::collection($userTenants);
    }

    public function show(ShowTenantUserRequest $request, TenantUser $userTenant): TenantUserResource
    {
        $userTenant = QueryBuilder::for($userTenant)
            ->allowedIncludes([
                'tenant',
                'user',
                'leadMember',
                'accessPrivileges',
                'locationAccessPrivileges',
                AllowedInclude::callback('locations', function ($query) use ($userTenant) {
                    $query->where('box_id', $userTenant->tenant_id);
                }),
            ])
            ->find($userTenant->getKey());

        return new TenantUserResource($userTenant);
    }

    public function delete(DeleteTenantUserRequest $request, TenantUser $userTenant): Response
    {
        $userTenant->update(['deleted' => true]);
        $userTenant->user->update(['linked_account_uuid' => null]);

        $userTenant->userLocation()
            ->active()
            ->whereRelation('location', 'box_id', $userTenant->tenant_id)
            ->update([
                'end_date' => today()->subDay(),
            ]);

        if ($userTenant->isMember()) {
            (new DebitBatchService())->deactivateFutureUserBatchesForUserBoxMembership($userTenant);
        }

        if ($userTenant->type === UserType::LEAD_MEMBER && $userTenant->leadMember) {
            $userTenant->leadMember->update([
                'deleted' => true,
            ]);
        }

        return response()->noContent();
    }

    public function bulkDelete(BulkDeleteTenantUserRequest $request, TenantUser $userTenant): Response
    {
        $tenantUsers = TenantUser::findOrFail($request->user_tenant_ids);

        foreach ($tenantUsers as $tenantUser) {

            if (! $tenantUser instanceof TenantUser) {
                continue;
            }

            if (! $tenantUser->isMember()) {
                continue;
            }

            $tenantUser->update(['deleted' => true]);
            $tenantUser->user->update(['linked_account_uuid' => null]);
        }

        return response()->noContent();
    }

    public function update(UpdateUserTenantRequest $request, TenantUser $userTenant): TenantUserResource
    {
        $authTenantUser = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $userTenant->tenant);

        if ($userTenant->type === UserType::LEAD_MEMBER) {
            abort(400, 'You cannot update a lead member.');
        }

        if ($request->has('type_id')) {
            if ($userTenant->user->is_redacted) {
                abort(400, 'Discovery leads cannot have user type updated. Please register a new user instead.');
            }

            if ($userTenant->type == UserType::LEAD_MEMBER) {
                abort(400, 'Lead member types cannot be updated, please convert the lead member to a gym member first.');
            }
        }

        if ($userTenant->user_id != auth()->user()->getAuthIdentifier()) {
            if ($request->has('type_id')) {
                // Update user
                $userType = UserType::from($request->type_id);
            } else {
                $userType = $userTenant->user_type_id;
            }

            // Cannot create or edit a super admin
            if (in_array($userType, [UserType::SUPER_ADMINISTRATOR, UserType::ADMIN])
                || in_array($userTenant->user_type_id, [UserType::SUPER_ADMINISTRATOR, UserType::ADMIN])
            ) {
                abort(401, 'Access denied.');
            }

            // Validate post data
            //validateUpdateUserBoxMembershipPostData
            if ($userType == UserType::GYM_MEMBER) {
                if (! $request->input('programme_id')) {
                    abort(400, 'Programme is required for gym members.');
                }

                if (! $request->input('location_id')) {
                    abort(400, 'Location is required for gym members.');
                }
            }

            // Edit a staff member (head coach, box admin, box facility admin, gym coach)
            if (in_array($userType, [UserType::HEAD_COACH, UserType::BOX_ADMIN, UserType::BOX_FACILITY_ADMIN, UserType::GYM_COACH, UserType::LOCATION_CHECK_IN])) {
                // Update the user
                (new TenantUserService())->updateStaffMemberBoxMembership(
                    $userTenant,
                    $request->safe()->except('lead')
                );
            }

            // Edit a gym member
            if ($userType == UserType::GYM_MEMBER) {
                $location = Location::find($request->location_id);

                // Checks is current member is one of these: (head coach, box admin, box facility admin)
                if ($authTenantUser && ($authTenantUser->isHeadCoach() || $authTenantUser->isTenantAdmin() || $authTenantUser->isLocationAdmin())) {

                    // Check if box facility belongs to this box if it's not super admin or region admin making the change
                    if ($authTenantUser->tenant_id !== $location->tenant_id) {
                        abort(400, 'This location does not belong to this facility.');
                    }

                    // Check if current user has access to make these changes
                    if (! (new AccessPrivilegeService)->hasAccessToResource($authTenantUser, 'member_actions')) {
                        abort(401, 'Access denied.');
                    }
                }

                $programme = Programme::findOrFail($request->programme_id);

                // Check if user's programme is allowed by package
                if (! (new ProgrammeService())->hasPackageWithProgrammeVisibility($programme, $userTenant->user)) {
                    // abort(400, 'The selected programme is not assigned to the user\'s current package(s).');
                }

                // Update the user
                (new TenantUserService())->updateGymMemberBoxMembership(
                    $userTenant,
                    $request->safe()->except('lead'),
                    $location);
            }
        } else {
            // update programme
            if ($request->has('programme_id')) {
                $programme = Programme::findOrFail($request->programme_id);

                // Check if user's programme is allowed by package
                if (! (new ProgrammeService())->hasPackageWithProgrammeVisibility($programme, $userTenant->user)) {
                    //abort(400, 'The selected programme is not assigned to the user\'s current package(s).');
                }

                $userTenant->programme_id = $programme->getKey();
            }

            if ($request->has('default_location_id')) {
                $location = Location::query()
                    ->where('box_facility_id', '=', $request->default_location_id)
                    ->where('box_id', '=', $userTenant->tenant_id)
                    ->active()
                    ->first();

                if (! $location) {
                    abort(400, 'The default location selected does not belong to this facility.');
                }

                $userTenant->default_location_id = $request->default_location_id;
            }

            $userTenant->fill([
                'bio' => $request->bio,
                'landing_screen' => $request->landing_screen,
            ]);

            $userTenant->save();
        }

        return new TenantUserResource($userTenant->load([
            'user', 'tenant', 'assignedCoach', 'defaultLocation', 'programme', 'region',
        ]));
    }

    public function getMemberPaymentDetails(GetPaymentDetailsRequest $request, TenantUser $userTenant): array
    {
        $discountDetails = null;
        $userBankingDetails = $userTenant->bankAccount;

        $currentLocationUser = (new TenantUserService())->getLocationUserByTenant($userTenant->user, $userTenant->tenant);
        $paymentGateway = $currentLocationUser?->location->paymentGateway;

        $paymentDetails = [
            'user_debit_status' => $userTenant->user_debit_status_id?->toArray(),
        ];

        if ($userTenant->user_debit_status_id === UserDebitStatus::CASH) {
            if ($userTenant->auto_invoicing_day && $userTenant->auto_invoicing_due_day) {
                $paymentDetails = array_merge($paymentDetails, [
                    'auto_invoicing_day' => $userTenant->auto_invoicing_day,
                    'auto_invoicing_due_day' => $userTenant->auto_invoicing_due_day,
                ]);
            }
        }

        if ($userTenant->user_debit_status_id === UserDebitStatus::DEBIT_ORDER) {
            if ($userBankingDetails) {
                $paymentDetails['debitDay'] = [
                    'id' => $userBankingDetails->debitDay->debit_day_id,
                    'name' => $userBankingDetails->debitDay->debit_day_descr,
                ];

                // If user has bankingDetails and payment gateway is netcash or three-peaks
                if ($paymentGateway && in_array($paymentGateway->payment_gateway_id, [PaymentGateway::SAGE_PAY_V2, PaymentGateway::THREE_PEAKS, PaymentGateway::SAGE_PAY_V3])) {
                    $userBankingDetailsData = [
                        'bank' => null,
                        'account_type' => null,
                        'account_number' => $userBankingDetails->account_no,
                        'account_holder_name' => $userBankingDetails->account_name,
                        'branch_code' => $userBankingDetails->branch_code,
                        'waiver' => $userBankingDetails->waiver,
                    ];

                    if ($userBankingDetails->bank()->exists()) {
                        $userBankingDetailsData['bank'] = [
                            'id' => $userBankingDetails->bank->bank_id,
                            'name' => $userBankingDetails->bank->bank_name,
                        ];
                    }

                    if ($userBankingDetails->accountType) {
                        $userBankingDetailsData['account_type'] = $userBankingDetails->accountType->toArray();
                    }

                    $paymentDetails = array_merge($paymentDetails, $userBankingDetailsData);
                } elseif ($paymentGateway?->payment_gateway_id === PaymentGateway::SEPA->value) {
                    $userBankingDetailsData = [
                        'iban' => $userBankingDetails->iban,
                        'bic' => $userBankingDetails->bic,
                        'account_holder_name' => $userBankingDetails->account_name,
                        'address' => $userBankingDetails->address,
                        'waiver' => $userBankingDetails->waiver,
                    ];

                    $paymentDetails = array_merge($paymentDetails, $userBankingDetailsData);
                }
            }
        }

        if ($userTenant->user_debit_status_id === UserDebitStatus::UP_FRONT_PAYMENT) {
            $latestUpfrontInvoice = (new InvoiceService())->getUserLatestUpfrontInvoice($userTenant->user, $userTenant->tenant);

            if ($latestUpfrontInvoice) {
                $paymentDetails = array_merge($paymentDetails, [
                    'upfront_invoice_start_date' => $latestUpfrontInvoice->period_start->toDateString(),
                    'upfront_invoice_end_date' => $latestUpfrontInvoice->period_end->toDateString(),
                ]);
            }
        }

        $userSpecialRate = SpecialRate::for($userTenant->user_id, $userTenant->tenant_id);

        if ($currentLocationUser?->location) {
            $userDiscount = (new LocationDiscountService())->getFacilityMembershipDiscountForUser($userTenant->user, $currentLocationUser?->location);
        } else {
            $userDiscount = null;
        }

        if ($userSpecialRate) {
            $discountDetails = [
                'type' => 'specialRate',
                'amount' => $userSpecialRate->amount,
            ];
        } elseif ($userDiscount instanceof LocationUserDiscount) {
            $discountDetails = [
                'type' => 'discount',
                'discount' => [
                    'id' => $userDiscount->discount?->discount_id,
                    'name' => $userDiscount->discount?->name,
                    'type' => $userDiscount->discount?->type,
                    'amount' => $userDiscount->discount?->amount,
                    'description' => $userDiscount->discount?->description,
                ],
            ];
        }

        return [
            'payment_details' => $paymentDetails,
            'discount_details' => $discountDetails,
        ];
    }

    public function updateMemberPaymentDetails(TenantUser $userTenant, UpdateTenantUserPaymentDetailsRequest $request)
    {
        if ($userTenant->type !== UserType::GYM_MEMBER) {
            abort(400, 'User has to be a gym member.');
        }

        $errors = [];

        $user = $userTenant->user;
        $tenant = $userTenant->tenant;
        $location = (new TenantUserService())->getLocationUserByTenant($user, $tenant)?->location;

        $discountDetails = $request->input('discount_details');
        $paymentDetails = $request->input('payment_details');

        if ($paymentDetails['debit_status_id'] == UserDebitStatus::DEBIT_ORDER->value) {
            if (in_array($location->payment_gateway_id, [PaymentGateway::THREE_PEAKS->value, PaymentGateway::SAGE_PAY_V2->value, PaymentGateway::SAGE_PAY_V3->value])) {
                $errors = (new FinanceService())->validateDebitOrderPaymentDetails($paymentDetails, $location);

                // Return errors if there are
                if ($errors) {
                    return response()->errorMessage(implode(',', $errors));
                }

                $bank = Bank::query()->find($paymentDetails['bank_id']);
                $accountType = AccountType::from($paymentDetails['account_type_id']);
                $accountNumber = $paymentDetails['account_number'];
                $bankBranchCode = $bank->universal_code ?: $paymentDetails['branch_code'];

                // Validate debit order banking details. Only validate banking details when in production mode
                $validationResult = [];

                if (in_array($location->payment_gateway_id, [PaymentGateway::SAGE_PAY_V2->value, PaymentGateway::SAGE_PAY_V3->value]) && (app()->isProduction())) {
                    $validationResult = (new NetcashService())->validateBankingDetails($location, $accountNumber, $bankBranchCode, $accountType);
                }

                if (is_string($validationResult)) {
                    $errors[] = $validationResult;
                } else {
                    $errors = [];
                }
            }
        }

        // Return errors if there are
        if ($errors) {
            return response()->errorMessage(implode(',', $errors));
        }

        $activeUserPackages = (new UserPackageService())->getActiveUserPackagesForTenant($userTenant->user, $userTenant->tenant);

        // Get userDebitStatus
        $userDebitStatus = UserDebitStatus::from($paymentDetails['debit_status_id']);

        if ($userDebitStatus === UserDebitStatus::UP_FRONT_PAYMENT && $activeUserPackages->isEmpty()) {
            abort(400, 'User does not have an active package.');
        }

        $existingSpecialRate = SpecialRate::for($userTenant->user_id, $userTenant->tenant_id);

        $userDiscount = (new FinanceService())->getDiscountForUserTenant($userTenant);

        if (is_array($discountDetails)) {
            if ($discountDetails['discount_type'] === 'specialRate') {
                $userDiscount?->disable();

                (new FinanceService())->createOrUpdateUserSpecialRate($userTenant, $discountDetails['amount']);
            } else {
                $existingSpecialRate?->disable();

                $discount = FinanceDiscount::query()->find($discountDetails['discount_id']);

                if ($discount instanceof FinanceDiscount) {
                    if ($userDiscount instanceof LocationUserDiscount && $userDiscount->discount->getKey() !== $discount->getKey()) {
                        // Update existing discount if discount has changed
                        $userDiscount->setAttribute('discount_id', $discount->getKey());
                        $userDiscount->setAttribute('starting_on', now());
                        $userDiscount->save();
                    }

                    if (! $userDiscount instanceof LocationUserDiscount) {
                        // Create new discount
                        (new FinanceService())->createFacilityMembershipDiscount((new TenantUserService())->getLocationUserByTenant($user, $tenant), $discount);
                    }
                }
            }
        } else {
            $existingSpecialRate?->disable();
            $userDiscount?->disable();
        }

        // Check if member was/is cash member
        $wasCashUser = $userTenant->debit_status === UserDebitStatus::CASH;

        // Cash member
        if ($userDebitStatus === UserDebitStatus::CASH) {
            // Check if the auto invoicing date is set
            if ($paymentDetails['invoicing_type'] === 'customDates') {
                $userTenant->setAttribute('auto_invoicing_day', $paymentDetails['auto_invoicing_day']);
                $userTenant->setAttribute('auto_invoicing_due_day', $paymentDetails['auto_invoicing_due_day']);
            } else {
                $userTenant->setAttribute('auto_invoicing_day', null);
                $userTenant->setAttribute('auto_invoicing_due_day', null);
            }
        } elseif ($wasCashUser && $userDebitStatus !== UserDebitStatus::CASH) {
            $userTenant->setAttribute('auto_invoicing_day', null);
            $userTenant->setAttribute('auto_invoicing_due_day', null);
        }

        // Check if member was/is debit order member
        $wasDebitOrderUser = $userTenant->debit_status === UserDebitStatus::DEBIT_ORDER;

        // Check if user is on hold

        $isOnHold = UserOnHold::query()
            ->where('user_id', $userTenant->user_id)
            ->where('box_id', $userTenant->box_id)
            ->first();

        // Get current banking details
        $userBankingDetails = $userTenant->bankAccount;

        // Debit order member
        if ($wasDebitOrderUser && $userDebitStatus !== UserDebitStatus::DEBIT_ORDER) {
            // Remove user from all future debit batches that have not yet been processed and also remove the invoices
            (new DebitBatchService())->deactivateFutureUserBatchesForUserBoxMembership($userTenant);

            if ($userBankingDetails instanceof UserBankingDetail) {
                // Disable banking details
                $userBankingDetails->update(['is_active' => false]);
            }
        } elseif (! $wasDebitOrderUser && $userDebitStatus == UserDebitStatus::DEBIT_ORDER && ! $isOnHold && $userTenant->status == UserStatus::ACTIVE) {
            $debitDay = DebitDay::query()->find($paymentDetails['debit_day_id']);

            $accountNumber = $paymentDetails['account_number'] ?? null;
            $accountHolderName = $paymentDetails['account_holder_name'] ?? null;
            $bank = isset($paymentDetails['bank_id']) ? Bank::query()->find($paymentDetails['bank_id']) : null;
            $accountType = isset($paymentDetails['account_type_id']) ? AccountType::from($paymentDetails['account_type_id']) : null;
            $waiverFile = array_key_exists('file', $paymentDetails) ? $paymentDetails['file'] : null;
            $branchCode = isset($paymentDetails['branch_code']) && ! empty($paymentDetails['branch_code']) ? $paymentDetails['branch_code'] : null;
            $iban = $paymentDetails['iban'] ?? null;
            $bic = $paymentDetails['bic'] ?? null;
            $address = $paymentDetails['address'] ?? null;

            $newUserBankingDetails = (new TenantUserService())->createOrUpdateUserBankingDetails($userTenant, $debitDay, $accountNumber, $accountHolderName, $accountType, $bank, $branchCode, $iban, $bic, $address);

            // Upload waiver if one was sent
            if ($waiverFile) {
                (new TenantUserService())->uploadUserBankingDetailsDocument($newUserBankingDetails, $waiverFile);
            }

            if ($activeUserPackages->isNotEmpty()) {
                (new DebitBatchService())->generateFutureDebitBatchesForUser($location, $debitDay, $user);
            }

            if ($location->payment_gateway_id === PaymentGateway::GO_CARDLESS->value) {
                $mandate = (new MandateService())->getMandateForUserAndLocation($user, $location, MandateStatus::activeStatusValues());

                if (! $mandate instanceof MandateGoCardless) {
                    // Send link
                    (new MandateService())->sendOnboardingMail($userTenant->user, $userTenant->tenant);
                }
            } elseif ($location->paymentGateway->isStripeConnect()) {
                $stripeConnectService = new StripeConnectService();

                $tenantPaymentMethods = $stripeConnectService->getDebitOrderPaymentMethodsForTenant($tenant);
                $paymentToken = (new PaymentTokenService())->getFinancePaymentToken($tenantPaymentMethods, FinancePaymentTokenType::USER, $user, $location);

                if (! $paymentToken instanceof FinancePaymentToken) {
                    $stripeConnectService->sendSetupIntentMail($userTenant);
                }
            }
        } elseif ($wasDebitOrderUser && $userDebitStatus === UserDebitStatus::DEBIT_ORDER) {
            if ($userBankingDetails instanceof UserBankingDetail) {
                // Disable banking details
                $userBankingDetails->update(['is_active' => false]);
            }

            $debitDay = DebitDay::query()->find($paymentDetails['debit_day_id']);

            $accountNumber = $paymentDetails['account_number'] ?? null;
            $accountHolderName = $paymentDetails['account_holder_name'] ?? null;
            $bank = isset($paymentDetails['bank_id']) ? Bank::query()->find($paymentDetails['bank_id']) : null;
            $accountType = isset($paymentDetails['account_type_id']) ? AccountType::from($paymentDetails['account_type_id']) : null;
            $waiverFile = array_key_exists('file', $paymentDetails) ? $paymentDetails['file'] : null;
            $branchCode = isset($paymentDetails['branch_code']) && ! empty($paymentDetails['branch_code']) ? $paymentDetails['branch_code'] : null;
            $iban = $paymentDetails['iban'] ?? null;
            $bic = $paymentDetails['bic'] ?? null;
            $address = $paymentDetails['address'] ?? null;

            $newUserBankingDetails = (new TenantUserService())->createOrUpdateUserBankingDetails($userTenant, $debitDay, $accountNumber, $accountHolderName, $accountType, $bank, $branchCode, $iban, $bic, $address);

            // Upload waiver if one was sent
            if ($waiverFile) {
                (new TenantUserService())->uploadUserBankingDetailsDocument($newUserBankingDetails, $waiverFile);
            }

            (new DebitBatchService())->deactivateAndRegenerateFutureUserBatchesForUser($userTenant, $newUserBankingDetails);
        }

        // Upfront paying member
        if ($userDebitStatus === UserDebitStatus::UP_FRONT_PAYMENT) {
            // Get payment period
            $upfrontPaymentStartDate = array_key_exists('upfront_payment_start_date', $paymentDetails) && $paymentDetails['upfront_payment_start_date'] != '' ? new \DateTime($paymentDetails['upfront_payment_start_date']) : new \DateTime();
            $upfrontPaymentEndDate = clone $upfrontPaymentStartDate;
            $upfrontPaymentEndDate->modify("+ {$paymentDetails['upfront_payment_period']} {$paymentDetails['upfront_payment_period_type']}");

            // Generate invoice for period and payment for invoice
            (new FinanceService())->generateUpfrontInvoiceAndPayment($userTenant, $paymentDetails['upfront_payment_amount'], $upfrontPaymentStartDate, $upfrontPaymentEndDate, InvoicePaymentType::from($paymentDetails['upfront_payment_method']));

            // Set the upfront period date for member
            $userTenant->update(['upfront_payment_end_date' => $upfrontPaymentEndDate]);
        }

        // Update userDebitStatus
        $userTenant->update(['debit_status' => $userDebitStatus]);

        return response()->noContent();

    }

    public function renewUpfrontPayment(TenantUser $userTenant, RenewUpFrontPaymentRequest $request): Response|JsonResponse
    {
        $paymentMethod = InvoicePaymentType::from($request->input('payment_method'));
        $amount = $request->input('amount');
        $upfrontPaymentPeriod = $request->input('upfront_payment_period');
        $upfrontPaymentPeriodType = $request->input('upfront_payment_period_type');

        if ($userTenant->user_debit_status_id != UserDebitStatus::UP_FRONT_PAYMENT) {
            abort(400, 'Cannot renew up front payment for user, payment type incorrect.');
        }

        if ($userTenant->upfront_payment_end_date === null) {
            abort(400, 'Cannot renew up front payment as user has not set upfront payment end date.');
        }

        $upfrontPaymentStartDate = $userTenant->upfront_payment_end_date;
        $upfrontPaymentEndDate = clone $upfrontPaymentStartDate;
        $upfrontPaymentEndDate->modify("+ $upfrontPaymentPeriod $upfrontPaymentPeriodType");

        // Generate invoice for period and payment for invoice
        $invoice = (new FinanceService())->generateUpfrontInvoiceAndPayment($userTenant, $amount, $upfrontPaymentStartDate, $upfrontPaymentEndDate, $paymentMethod);

        if (! $invoice instanceof UserInvoice) {
            abort(400, 'Invoice has not been generated.');
        }

        // Set the upfront period date for member
        $userTenant->update(['up_front_payment_end_date' => $upfrontPaymentEndDate]);

        return response([
            'user_debit_status' => $userTenant->debit_status,
            'upfront_invoice_start_date' => $invoice->period_start?->toDateString(),
            'upfront_invoice_end_date' => $invoice->period_end?->toDateString(),
        ]);
    }

    public function approveAllPendingMemberships(ApproveAllPendingTenantUserRequest $request): Response
    {
        $tenantUsers = TenantUser::query()
            ->where('user_status_id', '=', UserStatus::PENDING)
            ->where('box_id', '=', $request->input('tenant_id'))
            ->get();

        DB::transaction(function () use ($tenantUsers) {
            $crmService = (new CrmService());

            foreach ($tenantUsers as $tenantUser) {
                // Add member to mailing list that is for all active members
                $crmService->addUserToAllActiveMemberMailingLists($tenantUser->user, $tenantUser->tenant);

                // Send member welcome mailer
                $crmService->scheduleWelcomeMessageMailer($tenantUser->user, $tenantUser->tenant);
            }

            TenantUser::query()->whereKey($tenantUsers->modelKeys())->update([
                'user_status_id' => UserStatus::ACTIVE->value,
                'activated_on' => now(),
            ]);
        });

        return response()->noContent();
    }

    public function updateDebitStatus(UpdateDebitStatusRequest $request): Response
    {
        $tenant = Tenant::findOrFail($request->tenant_id);
        $userDebitStatus = UserDebitStatus::from($request->input('debit_status_id'));
        $debitDay = DebitDay::query()->find($request->input('debit_day_id'));
        $usersWithInvalidBanking = [];

        $userTenants = TenantUser::query()
            ->where('box_id', '=', $tenant)
            ->whereDate('end_date', '>', today())
            ->whereIn('user_id', $request->input('user_ids'))
            ->latest()
            ->groupBy('user_id')
            ->get();

        foreach ($userTenants as $userTenant) {
            if (! $userTenant instanceof TenantUser) {
                continue;
            }

            if (($userTenant->tenant_id != $request->input('tenant_id')) || $userTenant->type !== UserType::GYM_MEMBER) {
                continue;
            }

            $user = $userTenant->user;

            // Get current banking details
            $userBankingDetails = $userTenant->bankAccount;

            // If current debit-order member but payment type has changed remove all future debit batches
            if ($userTenant->debit_status === UserDebitStatus::DEBIT_ORDER && ($userTenant->debit_status !== $userDebitStatus)) {
                // Remove user from all future debit batches that have not yet been processed and also remove the invoices
                (new DebitBatchService())->deactivateFutureUserBatchesForUserBoxMembership($userTenant);

                if ($userBankingDetails instanceof UserBankingDetail) {
                    // Disable banking details
                    $userBankingDetails->setAttribute('is_active', false);
                    $userBankingDetails->save();
                }
            }

            // If changed to debit-order member
            if ($userDebitStatus === UserDebitStatus::DEBIT_ORDER) {
                // Remove user from all future debit batches that have not yet been processed and also remove the invoices
                (new DebitBatchService())->deactivateFutureUserBatchesForUserBoxMembership($userTenant);

                if ($userBankingDetails instanceof UserBankingDetail) {
                    // Update debit day
                    $userBankingDetails->setAttribute('debit_day_id', $debitDay->debit_day_id);
                    $userBankingDetails->save();
                } else {
                    // Create new Banking details
                    (new TenantUserService())->createOrUpdateUserBankingDetails($userTenant, $debitDay);
                }

                // Add user to new facility debit batches
                $location = (new TenantUserService())->getLocationUserByTenant($user, $userTenant->tenant)?->location;

                (new DebitBatchService())->generateFutureDebitBatchesForUser($location, $debitDay, $user);

                if ($location instanceof Location) {
                    if ($location->payment_gateway_id === PaymentGateway::GO_CARDLESS->value) {
                        $mandate = (new MandateService())->getMandateForUserAndLocation($user, $location, MandateStatus::activeStatusValues());

                        // Send link if user does not have one yet
                        if (! $mandate instanceof MandateGoCardless) {
                            (new MandateService())->sendOnboardingMail($user, $userTenant->tenant);
                        }
                    } elseif ($location->paymentGateway->isStripeConnect()) {
                        $stripeConnectService = new StripeConnectService();

                        $tenantPaymentMethods = $stripeConnectService->getDebitOrderPaymentMethodsForTenant($tenant);
                        $paymentToken = (new PaymentTokenService())->getFinancePaymentToken($tenantPaymentMethods, FinancePaymentTokenType::USER, $user, $location);

                        if (! $paymentToken instanceof FinancePaymentToken) {
                            $stripeConnectService->sendSetupIntentMail($userTenant);
                        }
                    }
                }
            }

            // Update user's userDebitStatus
            $userTenant->setAttribute('debit_status', $userDebitStatus);
            $userTenant->save();
        }

        if (! empty($usersWithInvalidBanking)) {
            abort(400, 'The following users have invalid banking details: '.implode(', ', $usersWithInvalidBanking));
        }

        return response()->noContent();
    }

    public function updateProgramme(UpdateProgrammeRequest $request): Response
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        foreach ($request->input('user_ids') as $userId) {
            $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenant);

            if (! $userTenant instanceof TenantUser) {
                continue;
            }

            if ($userTenant->type !== UserType::GYM_MEMBER) {
                continue;
            }

            $userTenant->update(['programme_id' => $request->input('programme_id')]);
        }

        return response()->noContent();
    }

    public function updateStatus(UpdateStatusRequest $request): Response
    {
        $tenant = Tenant::findOrFail($request->tenant_id);
        $status = UserStatus::from($request->status_id);

        $userTenantService = new TenantUserService();

        foreach ($request->input('user_ids') as $userId) {
            $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenant);

            if (! $userTenant instanceof TenantUser || in_array($userTenant->type, [UserType::SUPER_ADMINISTRATOR, UserType::ADMIN])
                || auth()->user()->getAuthIdentifier() === $userId
            ) {
                continue;
            }

            if (($userTenant->tenant_id != $request->input('tenant_id'))) {
                continue;
            }

            $userTenantService->updateStatus($userTenant, $status);
        }

        return response()->noContent();
    }

    public function updateHighRisk(UpdateHighRiskRequest $request): Response
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        foreach ($request->input('user_ids') as $userId) {
            $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenant);

            if (! $userTenant instanceof TenantUser) {
                continue;
            }

            if (($userTenant->tenant_id != $request->input('tenant_id')) || $userTenant->type !== UserType::GYM_MEMBER) {
                continue;
            }

            $userTenant->update(['high_risk' => $request->input('is_high_risk')]);
        }

        return response()->noContent();
    }

    public function store(StoreUserTenantRequest $request): TenantUserResource
    {
        $tenant = Tenant::find($request->tenant_id);
        $location = $request->input('location_id') ? Location::find($request->input('location_id')) : null;

        if ($location && $location->box_id !== $tenant->getKey()) {
            abort(400, 'This location does not belong to this facility.');
        }

        if ($request->input('type_id') === UserType::LEAD_MEMBER->value) {
            abort(400, 'You cannot create a lead member.');
        }

        if (in_array($request->type_id, [UserType::SUPER_ADMINISTRATOR->value, UserType::ADMIN->value])) {
            abort(401, 'Access denied.');
        }

        if ((new TenantUserService())->getCurrentUserTenantForTenant($request->input('user_id'), $request->input('tenant_id'))) {
            abort(400, 'This user already has an active membership at this facility.');
        }

        if ($request->input('type_id') == UserType::GYM_MEMBER->value) {
            // Create gym member
            $userBoxMembership = (new TenantUserService())->createGymMemberBoxMembership(
                User::query()->find($request->input('user_id')),
                UserType::GYM_MEMBER,
                [
                    ...$request->safe()->all(),
                ],
                $tenant,
                $location
            );
        } else {
            $userBoxMembership = (new TenantUserService())->createStaffMemberBoxMembership(
                User::query()->find($request->input('user_id')),
                UserType::from($request->input('type_id')),
                $request->validated(),
                $tenant,
                $location
            );
        }

        return new TenantUserResource($userBoxMembership);
    }

    public function sendWelcomeEmail(SendWelcomeEmailRequest $request)
    {
        SendWelcomeEmail::dispatch($request->input('tenant_id'), $request->input('users'));
    }
}
