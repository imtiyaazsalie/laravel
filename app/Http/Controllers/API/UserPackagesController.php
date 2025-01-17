<?php

namespace App\Http\Controllers\API;

use App\Enums\AccountType;
use App\Enums\FinancePaymentTokenType;
use App\Enums\InvoiceDiscriminator;
use App\Enums\LeadMemberStatus;
use App\Enums\LeadMemberType;
use App\Enums\MandateStatus;
use App\Enums\MandateType;
use App\Enums\PackageType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\WaiverStatus;
use App\Exports\UserPackagesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserPackage\BuyUserPackageRequest;
use App\Http\Requests\UserPackage\CoachTopUpRequest;
use App\Http\Requests\UserPackage\CreateUserPackageRequest;
use App\Http\Requests\UserPackage\DeactivateUserPackageRequest;
use App\Http\Requests\UserPackage\DeleteUserPackageRequest;
use App\Http\Requests\UserPackage\ExportUserPackagesRequest;
use App\Http\Requests\UserPackage\ListUserPackagesRequest;
use App\Http\Requests\UserPackage\MemberTopUpRequest;
use App\Http\Requests\UserPackage\PackagesAvailableForClassRequest;
use App\Http\Requests\UserPackage\UpdateUserPackageRequest;
use App\Http\Resources\UserInvoiceResource;
use App\Http\Resources\UserPackageResource;
use App\Models\Bank;
use App\Models\ClassDate;
use App\Models\DebitDay;
use App\Models\FinancePaymentToken;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\LocationUser;
use App\Models\Package;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserBatch;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Models\UserPackage;
use App\Services\ClassBookingsService;
use App\Services\ClassService;
use App\Services\CRM\DiscoveryNotificationsService;
use App\Services\CrmService;
use App\Services\DebitBatchService;
use App\Services\FinanceService;
use App\Services\InvoiceService;
use App\Services\LocationUserService;
use App\Services\MandateService;
use App\Services\PackageService;
use App\Services\PaymentGateways\GoCardlessService;
use App\Services\PaymentGateways\PaymentGatewayService;
use App\Services\PaymentGateways\StripeConnectService;
use App\Services\PaymentTokenService;
use App\Services\TenantUserService;
use App\Services\UserContractService;
use App\Services\UserPackageService;
use App\Services\UserService;
use App\Services\WidgetService;
use DateTime;
use Exception;
use GoCardlessPro\Core\Exception\InvalidStateException;
use Illuminate\Http\JsonResponse;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Maatwebsite\Excel\Facades\Excel;

class UserPackagesController extends Controller
{
    #[QueryParam('filter[package_id]', 'integer', required: false)]
    #[QueryParam('filter[user_id]', 'integer', required: false)]
    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[starts_between]', 'string', required: false)]
    #[QueryParam('filter[ends_between]', 'string', required: false)]
    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    #[QueryParam('filter[is_package_active]', 'boolean', required: false)]
    #[QueryParam('filter[is_sessions_available]', 'boolean', required: false)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('sort[user_name]', 'string', required: false)]
    #[QueryParam('sort[user_surname]', 'string', required: false)]
    public function list(ListUserPackagesRequest $request)
    {
        return UserPackageResource::collection((new UserPackageService)->getUserPackagesQueryBuilder($request)->_paginate());
    }

    public function export(ExportUserPackagesRequest $request)
    {
        $date = now()->toDateString();
        $tenant = Tenant::findOrFail($request->input('filter.tenant_id'));

        if ($request->has('filter.location_id')) {
            $location = Location::findOrFail($request->input('filter.location_id'));
            $filename = "{$location->name}_user_packages_$date.csv";
        } else {
            $filename = "{$tenant->name}_user_packages_$date.csv";
        }

        Excel::store(new UserPackagesExport(), $filename, 'tmp');

        return response()->json(Storage::disk('tmp')->temporaryUrl($filename, now()->addMinute()));
    }

    public function show(UserPackage $userPackage): UserPackageResource
    {
        return new UserPackageResource($userPackage->loadMissing('tenant.locations', 'invoices'));
    }

    public function buyPackage(BuyUserPackageRequest $request): JsonResponse
    {
        $errors = [];
        $package = Package::query()->findOrFail($request->get('package_id'));

        $authUserTenant = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $package->tenant_id);

        $location = (new TenantUserService())->getLocationUserByTenant($authUserTenant->user, $authUserTenant->tenant)?->location;

        if (! $location instanceof Location) {
            abort(400, 'Please make sure that you belong to a location. Please contact your studio for assistance.');
        }

        if (! $authUserTenant?->isMember()) {
            abort(403, 'Only gym members may buy packages.');
        }

        if (! $package instanceof Package) {
            abort(404, 'Package could not be found.');
        }

        if ($authUserTenant->isLeadMember() && $authUserTenant->user->is_redacted && $package->type !== PackageType::DROP_IN) {
            abort(400, 'Discovery lead members may only buy drop in packages.');
        }

        $user = $request->user();

        $tenant = $location->tenant;
        $paymentDetails = $request->input('payment_details');
        $isLimitedPackage = $package->package_limit_type_id === PackageType::LIMITED;

        // Check if the box makes use of contracts and waivers and if they have been accepted
        if ($tenant->signup_use_contract_and_waivers) {
            $isTermsAndConditionsForWaiverTrue = boolval($request->get('terms_and_conditions_for_waiver')) === true;
            $isTermsAndConditionsForContractTrue = boolval($request->get('terms_and_conditions_for_contract')) === true;

            if (! $isTermsAndConditionsForWaiverTrue) {
                $errors['terms_and_conditions_for_waiver'] = 'Waiver terms and conditions is required';
            }

            if (! $isTermsAndConditionsForContractTrue) {
                $errors['terms_and_conditions_for_contract'] = 'Terms and conditions for contract is required';
            }
        }

        // Check if the payment method has been sent through and if package is not limited

        if ((! $isLimitedPackage) && ! $request->input('payment_details.method')) {
            $errors['method'] = 'Payment method is a required field';
        }

        // Get boxFacility's payment gateway settings for current sign-up payment gateway settings
        $boxFacilityPaymentGateway = (new PaymentGatewayService())->getBoxFacilityPaymentGatewayBySettings($location, $isLimitedPackage);

        $selectedLocationPaymentGateway = $location->paymentGateway;

        // If limited package: set the user to no payment
        if ($isLimitedPackage && $boxFacilityPaymentGateway instanceof LocationPaymentGateway) {
            $paymentMethod = UserDebitStatus::NO_PAYMENT->value;

            if (in_array($boxFacilityPaymentGateway->payment_gateway_id, [PaymentGateway::SAGE_PAY_V2->value, PaymentGateway::SAGE_PAY_V3->value])) {
                $paymentMethodName = 'Netcash: pay-now';
            } else {
                $paymentMethodName = $boxFacilityPaymentGateway->paymentGateway->payment_gateway_name;
            }

        } elseif ($isLimitedPackage) {
            // Set to cash so that invoice can be generated for user
            $paymentMethod = UserDebitStatus::CASH->value;
            $paymentMethodName = 'Cash/EFT/Card';
        } else {
            $paymentMethod = Arr::get($paymentDetails, 'method');
            $paymentMethodName = $paymentMethod ? UserDebitStatus::from($paymentMethod)->name : null;
        }

        $isPaymentMethodDebitOrder = (int) $paymentMethod === UserDebitStatus::DEBIT_ORDER->value;

        // Validate banking details if the user selected debit order
        if ($isPaymentMethodDebitOrder) {
            $bankingDetailsErrors = (new FinanceService())->validateDebitOrderPaymentDetails($paymentDetails, $location);

            // Merge the errors
            $errors = array_merge($errors, $bankingDetailsErrors);
        }

        if (count($errors) > 0) {
            return response()->json($errors, 400);
        }

        $packageEndDate = $package->getDefaultPeriodIntervalDate();

        // Create user contract
        if ($tenant->signup_use_contract_and_waivers) {
            (new UserService())->createUserDigitalLeadWaiver($user, $location, WaiverStatus::SIGNED->value, $request->getClientIp());
            (new UserContractService())->createOrUpdateUserContract(null, $user, $location, null, $packageEndDate, null, true, $request->getClientIp());
        }

        // Create user package membership(userToPackage)
        $userPackage = (new PackageService())->createUserPackage($user, $package, null, $packageEndDate);

        if (! $userPackage instanceof UserPackage) {
            abort(400, 'User package could not be created. Please contact your location for assistance.');
        }

        // For debit order members only
        if ($isPaymentMethodDebitOrder) {
            $debitDay = DebitDay::query()->find($paymentDetails['debit_day_id']);
            $accountNumber = $paymentDetails['account_number'] ?? null;
            $accountHolderName = $paymentDetails['account_holder_name'] ?? null;
            $bank = isset($paymentDetails['bank_id']) ? Bank::query()->find($paymentDetails['bank_id']) : null;
            $accountType = isset($paymentDetails['account_type_id']) ? AccountType::from($paymentDetails['account_type_id'])->value : null;
            $branchCode = $paymentDetails['branch_code'] ?? null;
            $iban = $paymentDetails['iban'] ?? null;
            $bic = $paymentDetails['bic'] ?? null;
            $address = $paymentDetails['address'] ?? null;

            // Generate debit batches
            $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($user, $package->tenant_id);

            if ($userTenant instanceof TenantUser) {
                // Create user's banking details
                $newUserBankingDetails = (new TenantUserService())->createOrUpdateUserBankingDetails($userTenant, $debitDay, $accountNumber, $accountHolderName, $accountType, $bank, $branchCode, $iban, $bic, $address);
                (new DebitBatchService())->deactivateAndRegenerateFutureUserBatchesForUser($userTenant, $newUserBankingDetails);
            }
        }

        // For debit order members only: Add new package to next invoice
        if ($isPaymentMethodDebitOrder) {
            $requiresProRateInvoice = $tenant->pro_rate_strategy === 'automatic';

            // Get the next upcoming unprocessed user debit batch
            $futureUserBatches = (new DebitBatchService())->getAllUnprocessedActiveBatchesForUser($user, $tenant);
            $nextDebitBatch = $futureUserBatches[0] ?? null;

            if ($nextDebitBatch instanceof UserBatch && $requiresProRateInvoice) {
                // Get next upcoming debit batch for user to adjust prorate for new package
                $invoice = $nextDebitBatch->invoice;

                $proRateLineItem = new UserInvoiceItem();

                // Create invoice line item
                $proRateLineItem
                    ->setAttribute('invoice_id', $invoice->getKey())
                    ->setAttribute('discriminator', 'prorate')
                    ->setAttribute('description', $userPackage->package->package_name.' (Prorate)')
                    ->setAttribute('quantity', 1)
                    ->setAttribute('unitPrice', $userPackage->package->getExclusiveProrateAmount())
                    ->setAttribute('amount', $userPackage->package->getExclusiveProrateAmount());

                $proRateLineItem->save();

                // Pro-rate only applies for the next user batch
                $requiresProRateInvoice = false;

                $invoice->recalculateTotal();
                $nextDebitBatch->update([
                    'amount' => $invoice->amount,
                ]);
                (new DebitBatchService())->updateBatchTotal($nextDebitBatch->debitBatch);
            } elseif (! $nextDebitBatch instanceof UserBatch) {
                // Generate an invoice for a user
                $invoice = (new FinanceService())->generateInvoiceForNewUserPackage($user, $location, $userPackage, $package->package_price);

                if ($invoice instanceof UserInvoice) {
                    $adhocPaymentGatewayId = null;
                    $adhocBoxFacilityPaymentGateways = (new PaymentGatewayService())->getAppropriateLocationPaymentGateways($tenant, $location, PaymentGatewayContext::AD_HOC, true);

                    foreach ($adhocBoxFacilityPaymentGateways as $adhocBoxFacilityPaymentGateway) {
                        $adhocPaymentGatewayId = $adhocBoxFacilityPaymentGateway->getKey();
                        break;
                    }

                    // Send the coach and the member an email notifying them of what has happened.
                    (new CrmService())->sendNoFutureBatchesNotifications($user, $location, $invoice, $adhocPaymentGatewayId);
                }
            }
        }

        $data = [];
        $isAdhoc = $isLimitedPackage && ($paymentMethod == UserDebitStatus::CASH->value || $paymentMethod == UserDebitStatus::NO_PAYMENT->value);

        if ($isAdhoc) {
            // Generate an invoice for a user
            $invoice = (new FinanceService())->generateInvoiceForNewUserPackage($user, $location, $userPackage, $package->package_price);

            $data = [
                'invoice_id' => $invoice->getKey(),
                'box_facility_payment_gateway_id' => $boxFacilityPaymentGateway instanceof LocationPaymentGateway ? $boxFacilityPaymentGateway->getKey() : null,
            ];

            // Set sessions to 0 until payment is successful
            $userPackage->update([
                'sessions_available' => 0,
            ]);

            // This is so that the sessions can be released once invoice has been paid
            $invoice->update([
                'discriminator' => InvoiceDiscriminator::BUY_PACKAGE_INVOICE->value,
            ]);

            if ($boxFacilityPaymentGateway instanceof LocationPaymentGateway) {
                $invoice->update([
                    'facility_to_payment_gateway_id' => $boxFacilityPaymentGateway->getKey(),
                ]);
            }
        } elseif (($location->paymentGateway->isGoCardless() && $isPaymentMethodDebitOrder) && ! (new GoCardlessService())->isUserOnBoard($user, $location)) {
            try {
                $data['link'] = (new GoCardlessService())->beginUserOnBoardingFlow($user, $location);
            } catch (InvalidStateException $e) {
                abort(400, $e->getMessage());
            } catch (Exception $e) {
                abort(400, 'Location has not yet been on-boarded to GoCardless.');
            }
        } elseif (($location->paymentGateway->isSepa() && $isPaymentMethodDebitOrder) && ! (new MandateService())->getLatestMandateByStatus($user, $tenant, MandateStatus::ACTIVE, MandateType::SEPA)) {
            $data['link'] = config('octiv.web_app_url').'/sign/mandate';
        } elseif ($location->paymentGateway->isStripeConnect() && $isPaymentMethodDebitOrder) {
            try {
                $stripeConnectService = new StripeConnectService();

                $tenantPaymentMethods = $stripeConnectService->getDebitOrderPaymentMethodsForTenant($tenant);
                $paymentToken = (new PaymentTokenService())->getFinancePaymentToken($tenantPaymentMethods, FinancePaymentTokenType::USER, $user, $location);

                if (! $paymentToken instanceof FinancePaymentToken) {
                    $data['link'] = $stripeConnectService->setupIntent($user, $tenantPaymentMethods, $location);
                }
            } catch (Exception $e) {
                abort(400, $e->getMessage());
            }
        }

        if (! $isLimitedPackage && $paymentMethod == UserDebitStatus::CASH->value) {
            $requiresProRateInvoice = $tenant->pro_rate_strategy === 'automatic';

            $rate = $requiresProRateInvoice ? $package->getExclusiveProrateAmount() : $package->package_price;

            // Generate cash invoice for user
            (new FinanceService())->generateInvoiceForNewUserPackage($user, $location, $userPackage, $rate);

            if (! $authUserTenant->isDebitOrder()) {
                $authUserTenant->user_debit_status_id = UserDebitStatus::CASH;
                $authUserTenant->save();
            }
        }

        $headCoaches = TenantUser::query()
            ->headCoaches()
            ->active()
            ->with('user')
            ->where('box_id', $tenant->getKey())
            ->get();

        $coachesCcArray = $headCoaches->pluck('user.email');

        if (count($coachesCcArray) > 0) {
            $firstHeadCoach = $coachesCcArray[0];
            unset($coachesCcArray[0]);

            // Send notification to coaches
            $emailContent = Markdown::parse(
                view('emails.package-bought', [
                    'user' => $user,
                    'package' => $package,
                    'paymentMethodName' => $paymentMethodName->name ?? $paymentMethodName,
                ])
            )->__toString();

            (new CrmService())->createScheduledEmail(
                $emailContent,
                'Member Purchased New Package',
                $firstHeadCoach,
                'noreply@octivfitness.com',
                null,
                $coachesCcArray->toArray()
            );
        }

        return response()->json($data, 201);
    }

    public function deactivate(DeactivateUserPackageRequest $request, UserPackage $userPackage): UserPackageResource
    {
        $userPackage->update(['end_date' => new DateTime('yesterday')]);

        $user = $userPackage->user;
        $tenant = $userPackage->package->tenant;

        // Deactivate and re-create user batches for debit order members
        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($user, $tenant);

        if ($userBoxMembership instanceof TenantUser && $userBoxMembership->user_debit_status_id === UserDebitStatus::DEBIT_ORDER) {
            (new DebitBatchService())->deactivateAndRegenerateFutureUserBatchesForUser($userBoxMembership, $userBoxMembership->bankAccount);
        }

        return new UserPackageResource($userPackage);
    }

    public function memberTopUp(MemberTopUpRequest $request, UserPackage $userPackage): JsonResponse
    {
        $unpaidInvoice = (new InvoiceService())->getExistingUnpaidTopupInvoiceForUserPackage($userPackage);

        if ($unpaidInvoice instanceof UserInvoice) {
            abort(400, 'You already have an existing unpaid top-up invoice. Please first ensure that this invoice is paid before purchasing another top-up');
        }

        $userFacilityMembership = (new LocationUserService())->getOldestActiveFacilityMembershipForUserAndBox($request->user(), $userPackage->package->tenant);

        if (! $userFacilityMembership instanceof LocationUser) {
            abort(404, 'User has no active location membership');
        }

        // Check if this package allowed to be topped up
        if ($userPackage->package->package_limit_type_id !== PackageType::LIMITED && $userPackage->package->package_limit === 0) {
            abort(400, 'Package cannot be topped-up');
        }

        if (! $userPackage->package->package_topup_price) {
            abort(400, 'Package cannot be topped-up, it has no top-up price.');
        }

        // Generate an invoice for a user
        $invoice = (new InvoiceService())->generateTopUpInvoiceForUserPackage($userPackage, $userFacilityMembership, (int) $request->get('sessions'), now());

        $boxFacilityPaymentGateway = (new PaymentGatewayService())->getBoxFacilityPaymentGatewayBySettings($userFacilityMembership->location, true);

        if (! $boxFacilityPaymentGateway) {
            abort(400, 'You have been invoiced for this top-up but your studio will need to manually process your payment in order for your sessions to become active. Please get in touch with your studio to do this.');
        }

        $invoice->update([
            'facility_to_payment_gateway_id' => $boxFacilityPaymentGateway->getKey(),
        ]);

        return response()->json([
            'invoiceId' => $invoice->getKey(),
            'locationPaymentGatewayId' => $boxFacilityPaymentGateway->getKey(),
        ]);
    }

    public function coachTopUp(CoachTopUpRequest $request, UserPackage $userPackage)
    {
        $userFacilityMembership = (new LocationUserService())->getOldestActiveFacilityMembershipForUserAndBox($userPackage->user, $userPackage->package->tenant);

        if (! $userFacilityMembership instanceof LocationUser) {
            abort(400, 'User has no active location membership');
        }

        $package = $userPackage->package;

        // Check if this package allowed to be topped up
        if ($package->package_limit_type_id !== PackageType::LIMITED && $package->package_limit === 0) {
            abort(400, 'Package cannot be topped-up');
        }

        if (! $userPackage->package->package_topup_price) {
            abort(400, 'Package cannot be topped-up, it has no top-up price.');
        }

        $paymentType = $request->get('payment_type');
        $sessions = (int) $request->get('sessions');

        // Process payment
        switch ($paymentType) {
            case 'cash':
            case 'card':
            case 'eft':

                // Generate an invoice for a user
                $invoice = (new InvoiceService())->generateTopUpInvoiceForUserPackage($userPackage, $userFacilityMembership, $sessions, now());

                // Create payment for invoice and release the sessions
                (new InvoiceService())->createPaymentForInvoice($invoice, $paymentType, $invoice->amount, null, "Trainer membership top-up payment: $paymentType");

                break;

            case 'debitOrder':

                $invoice = (new UserPackageService())->processDebitOrderCoachTopup($userPackage, $sessions);

                if (! $invoice instanceof UserInvoice) {
                    abort(400, 'Package could not be topped up because debit-order invoice does not exist.');
                }

                // Assign sessions to user package
                $currentSessionsCount = $userPackage->sessions_available ?? 0;

                if ($sessions > 0) {
                    $userPackage->update(['sessions_available' => $currentSessionsCount]);
                } else {
                    $userPackage->update(['sessions_available' => null]);
                }

                break;

            case 'adhocPayment':
            case 'invoiceOnly':

                // Generate an invoice for a user
                $invoice = (new InvoiceService())->generateTopUpInvoiceForUserPackage($userPackage, $userFacilityMembership, $sessions, now());

                break;

            case 'noPayment':

                // Assign sessions to user package
                $currentSessionsCount = $userPackage->sessions_available ?? 0;

                if ($sessions > 0) {
                    $userPackage->update(['sessions_available' => $currentSessionsCount + $sessions]);
                } else {
                    $userPackage->update(['sessions_available' => null]);
                }

                break;

            default:

                abort(400, 'Package could not be topped up. Something went wrong during the payment process.');

        }

        if (! isset($invoice)) {
            return response()->noContent();
        }

        if ($paymentType !== 'no_payment' && ! $invoice instanceof UserInvoice) {
            abort(400, 'Invoice could not be generated.');
        }

        if ($invoice instanceof UserInvoice) {
            if ($request->get('is_send')) {
                (new InvoiceService())->sendInvoice($invoice);
            }
        }

        return new UserInvoiceResource($invoice);
    }

    public function store(CreateUserPackageRequest $request): UserPackageResource
    {
        if ($request->user()->tokenCan('discovery-vitality')) {

            $user = User::find($request->input('user_id'));
            $package = Package::find($request->input('package_id'));

            if ($package->type != PackageType::DROP_IN) {
                abort(400, 'Member can only purchase drop_in package');
            }

            $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($user, $package->tenant);
            $locations = $package->tenant->locations->where('is_active', 1);

            if (! $userTenant) {
                (new TenantUserService())->createUserBoxMembership(
                    user: $user,
                    tenant: $package->tenant,
                    userType: UserType::LEAD_MEMBER,
                    userDebitStatus: UserDebitStatus::NO_PAYMENT,
                    userStatus: UserStatus::ACTIVE,
                );

                foreach ($locations as $location) {
                    $leadMember = LeadMember::firstOrCreate([
                        'type' => LeadMemberType::REFERRAL,
                        'source' => 'discovery',
                        'status' => LeadMemberStatus::DROP_IN,
                        'box_facility_id' => $location->box_facility_id,
                        'user_id' => $user->user_id,
                    ]);

                    if ($leadMember->wasRecentlyCreated) {
                        (new TenantUserService())->createUserFacilityMembership($leadMember, $location);
                    }
                }
            }

            $userPackage = (new UserPackageService())->storeLeadUserPackage(
                $user,
                $package
            );

            if ($request->has('class_date_id')) {
                $classDate = ClassDate::find($request->input('class_date_id'));

                $box = $classDate->class->tenant;
                $boxFacility = $classDate->class->location;
                $timezone = $boxFacility->timezone ? $boxFacility->timezone : $box->timezone;

                // Box booking threshold check
                $bookingThresholdDate = new DateTime('now', new \DateTimeZone($timezone->zone));
                $bookingThresholdDate->modify('+'.$box->booking_threshold.' days');

                // Get classTime
                $startTime = (new ClassService())->getClassDateStartDateTime($classDate);
                $classDateTime = clone $classDate->date;
                $classDateTime->setTimezone(new \DateTimeZone($timezone->zone));
                $classDateTime->setTime($startTime->format('H'), $startTime->format('i'), $startTime->format('s'));

                if ($classDateTime > $bookingThresholdDate) {
                    $userPackage->forceDelete();

                    abort(400, 'You cannot book this far in advance');
                }

                $canMemberBookOrErrorMessage = (new ClassService())->canPackageLeadMemberBookForClassDate($classDate, null, true);

                if (is_string($canMemberBookOrErrorMessage)) {
                    $userPackage->forceDelete();
                    abort(400, $canMemberBookOrErrorMessage);
                }

                $leadMember = LeadMember::where('box_facility_id', $boxFacility->getKey())
                    ->where('user_id', $request->input('user_id'))
                    ->first();

                $classBooking = (new ClassService())->createClassBookingForClassDateAndLeadByUser(classDate: $classDate, lead: $leadMember, userPackage: $userPackage);

                (new DiscoveryNotificationsService())->sendBookingConfirmationToMember($classBooking);
                (new DiscoveryNotificationsService())->sendBookingConfirmationToCoach($classBooking);

                $userPackage = $classBooking->userPackage;
                $userPackage->setAttribute('class_booking', $classBooking->withoutRelations()->load('classDate'));

                return new UserPackageResource($classBooking->userPackage);
            }

            return new UserPackageResource($userPackage->load('invoices'));
        }

        $package = Package::query()->findOrFail($request->safe()->collect()->get('package_id'));
        $startingOn = $request->safe()->collect()->get('starting_on') ? new DateTime($request->safe()->collect()->get('starting_on')) : new DateTime();
        $endingOn = $request->safe()->collect()->get('ending_on') ? new DateTime($request->safe()->collect()->get('ending_on')) : null;
        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($request->safe()->collect()->get('user_id'), $package->tenant);

        $authUserTenant = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $package->tenant);

        if ((! $authUserTenant || $authUserTenant->isMember()) && auth()->user()->getAuthIdentifier() != $request->user_id) {
            abort(400, 'You are not allowed to create a package for another user.');
        }

        if (! $userBoxMembership instanceof TenantUser) {
            $userBoxMembership = (new TenantUserService())->createUserBoxMembership(
                user: User::findOrFail($request->safe()->collect()->get('user_id')),
                tenant: $package->tenant,
                userType: UserType::GYM_MEMBER,
                startingDate: today(),
                programme: Programme::query()->where('box_id', '=', $package->tenant->getKey())->first() ?? null,
                userDebitStatus: UserDebitStatus::CASH,
                userStatus: UserStatus::ACTIVE
            );
            $userBoxMembership->refresh();
        }

        if ($request->has('class_date_id')) {
            $classDate = ClassDate::query()->find($request->get('class_date_id'));

            $locationUser = LocationUser::query()
                ->where('user_id', $request->get('user_id'))
                ->where('box_facility_id', $classDate->class->box_facility_id)
                ->first();

            if (! $locationUser) {
                $user = User::query()->find($request->get('user_id'));
                $location = Location::query()->find($classDate->class->box_facility_id);
                (new TenantUserService())->createUserFacilityMembership($user, $location);
            }
        }

        if (! $package instanceof Package) {
            abort(400, 'Package could not be found.');
        }

        if ($package->box_id !== $userBoxMembership->box_id) {
            abort(400, 'Package could not be found for this facility.');
        }

        if (! $package->is_active == 1) {
            abort(400, 'The selected package is not active.');
        }

        if ($endingOn instanceof DateTime && $endingOn < $startingOn) {
            abort(400, 'Ending on date has to be after the starting on date.');
        }

        if ($authUserTenant->isLeadMember() && ! $package->is_display_on_buy_packages) {
            abort(400, 'Lead members may not buy this package.');
        }

        $user = $userBoxMembership->user;

        $userPackage = new UserPackage();

        $userPackage->setAttribute('user_id', $user->getKey());
        $userPackage->setAttribute('package_id', $package->getKey());
        $userPackage->setAttribute('effective_date', $startingOn);
        $userPackage->setAttribute('end_date', $endingOn);

        if ($package->package_limit_type_id === PackageType::LIMITED) {
            $userPackage->setAttribute('sessions_available', 0);
        }

        $userPackage->save();

        // Deactivate and re-create user batches for debit order members
        $userBankingDetails = $userBoxMembership->bankAccount;

        if ($userBankingDetails && $userBoxMembership->user_debit_status_id === UserDebitStatus::DEBIT_ORDER && $package->package_limit_type_id !== PackageType::LIMITED) {
            (new DebitBatchService())->deactivateAndRegenerateFutureUserBatchesForUser($userBoxMembership, $userBankingDetails);
        }

        $invoiceDetails = $request->safe()->collect()->get('invoice');

        if ($invoiceDetails) {
            $paymentType = Arr::get($invoiceDetails, 'payment_type');
            $amount = Arr::has($invoiceDetails, 'amount')
                ? preg_replace('/[^0-9.]/', '', Arr::get($invoiceDetails, 'amount'))
                : null;

            if (! in_array($paymentType, ['cash', 'card', 'eft', 'adhocPayment', 'invoiceOnly'])) {
                abort(400, 'Please send through a valid payment type.');
            }

            if (! $amount || $amount == '') {
                abort(400, 'Amount is a required field for invoice details.');
            }

            $location = (new TenantUserService())->getLocationUserByTenant($user, $userBoxMembership->tenant)?->location;

            if (! $location) {
                abort(400, 'Could not find user location for tenant.');
            }

            $invoice = (new FinanceService())->generateInvoiceForNewUserPackage($user, $location, $userPackage, $amount);

            $invoice->update([
                'discriminator' => InvoiceDiscriminator::ALLOCATED_PACKAGE_INVOICE->value,
            ]);

            if (in_array($paymentType, ['cash', 'card', 'eft'])) {
                (new InvoiceService())->createPaymentForInvoice($invoice, $paymentType, (float) $amount, null, "Trainer package creation payment: $paymentType");
            }

            if (isset($invoiceDetails['is_send']) && filter_var($invoiceDetails['is_send'], FILTER_VALIDATE_BOOLEAN)) {
                (new InvoiceService())->sendInvoice($invoice);
            }

            if ($authUserTenant->isLeadMember() && in_array($paymentType, ['cash'])) {
                (new WidgetService())->sendDropInSuccessNotifications($package);
            }

        } elseif ($package->package_limit_type_id === PackageType::LIMITED) {
            $userPackage->setAttribute('sessions_available', $package->package_limit);
        }

        $userPackage->save();

        if ($request->safe()->has('class_date_id')) {
            if ($package->tenant_id != ClassDate::query()->find($request->safe()->collect()->get('class_date_id'))->class->tenant_id) {
                $userPackage->forceDelete();
                abort(400, 'The class and package tenants do not match');
            }
            try {
                $userPackage->refresh();
                $classBooking = (new ClassBookingsService())->createBooking($request, $userPackage);
                $userPackage->setAttribute('class_booking', $classBooking);
            } catch (Exception $e) {
                $userPackage->forceDelete();
                abort(400, $e->getMessage());
            }
        }

        return new UserPackageResource($userPackage->load('invoices'));
    }

    public function update(UpdateUserPackageRequest $request, UserPackage $userPackage): UserPackageResource
    {
        $user = $userPackage->user;
        $startingOn = new DateTime($request->get('starting_on'));
        $endingOn = $request->get('ending_on') ? new DateTime($request->get('ending_on')) : null;
        $wasActive = $userPackage->isActive();

        $activeCount = (new UserPackageService())->getActiveUserPackagesForTenant($user, $userPackage->package->tenant)->count();

        if ($endingOn instanceof DateTime && $endingOn < $startingOn) {
            abort(400, 'Ending on date has to be after the starting on date.');
        }

        $userPackage
            ->setAttribute('effective_date', $startingOn)
            ->setAttribute('end_date', $endingOn);

        if ($activeCount === 1 && ! $userPackage->isActive() && $wasActive) {
            abort(400, 'You may not deactivate all packages for a member. At least one package should always be active.');
        }

        // Update session available if it's a limited package'
        if ($userPackage->package->package_limit_type_id === PackageType::LIMITED) {
            $sessionsAvailable = $request->get('sessions_available');

            if (is_numeric($sessionsAvailable)) {
                $userPackage->setAttribute('sessions_available', $sessionsAvailable);
            } else {
                abort(400, 'Sessions available is a required field and must be greater than or equal to 0.');
            }
        }

        $userPackage->save();

        // Deactivate and re-create user batches for debit order members
        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($user, $userPackage->package->tenant);

        if ($userBoxMembership instanceof TenantUser && $userBoxMembership->user_debit_status_id === UserDebitStatus::DEBIT_ORDER) {
            (new DebitBatchService())->deactivateAndRegenerateFutureUserBatchesForUser($userBoxMembership, $userBoxMembership->bankAccount);
        }

        return new UserPackageResource($userPackage);
    }

    public function delete(DeleteUserPackageRequest $request, UserPackage $userPackage)
    {
        $userPackage->update([
            'deleted' => true,
        ]);

        return response()->noContent();
    }

    public function packagesAvailableForClass(PackagesAvailableForClassRequest $request)
    {
        $athlete = User::query()->findOrFail($request->input('user_id'));
        $classDate = ClassDate::query()->findOrFail($request->input('class_date_id'));

        return UserPackageResource::collection((new UserPackageService())->packagesAvailableForClass($athlete, $classDate));

    }
}
