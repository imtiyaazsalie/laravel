<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoiceItemDiscriminator;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PackageType;
use App\Enums\UserDebitStatus;
use App\Enums\UserType;
use App\Exceptions\TenantUser\NoActivePackagesException;
use App\Models\Bank;
use App\Models\ClassBooking;
use App\Models\DebitBatch;
use App\Models\DebitDay;
use App\Models\FinanceTopUp;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\LocationInvoice;
use App\Models\LocationUser;
use App\Models\LocationUserDiscount;
use App\Models\SpecialRate;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserBatch;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Models\UserInvoicePayment;
use App\Models\UserPackage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DateTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

class InvoiceService
{
    public function createSignUpInvoice(TenantUser $tenantUser, UserPackage $userPackage, Location $location, int $paymentMethod, ?array $paymentDetails = null): ?UserInvoice
    {
        $invoice = null;
        $user = $userPackage->user;
        $package = $userPackage->package;
        $tenant = $location->tenant;
        $financeService = (new FinanceService());

        // Cash/EFT paying user
        if ($paymentMethod == UserDebitStatus::CASH->value || $paymentMethod == UserDebitStatus::NO_PAYMENT->value) {
            // Get the package amount
            if ($tenant->pro_rate_strategy === 'manual' || $package->type === PackageType::LIMITED) {
                $packageAmount = $package->price;
            } else {
                $packageAmount = $package->getExclusiveProrateAmount();
            }

            // Generate an invoice for a user
            $invoice = $financeService->generateInvoiceForNewUserPackage($user, $location, $userPackage, $packageAmount);
        }

        // Debit order paying user
        if ($paymentMethod == UserDebitStatus::DEBIT_ORDER->value) {
            $debitDay = DebitDay::find($paymentDetails['debit_day_id']);
            $futureDebitBatches = (new DebitBatchService())->getFutureDebitDates($location->getKey(), $paymentDetails['debit_day_id']);
            $requiresProRateInvoice = $tenant->pro_rate_strategy === 'automatic';

            foreach ($futureDebitBatches as $debitBatch) {
                $debitBatchEntity = DebitBatch::query()->findOrFail($debitBatch->debit_batch_id);

                if ($requiresProRateInvoice) {
                    $invoice = $financeService->generateProRateInvoiceForUser($userPackage, $debitBatchEntity);
                    $requiresProRateInvoice = false;
                } else {
                    $invoice = $financeService->generateInvoiceForUser($user, $debitBatchEntity);
                }

                if ($invoice instanceof UserInvoice) {
                    $invoice->update([
                        'discriminator' => InvoiceDiscriminator::SIGN_UP_INVOICE,
                    ]);

                    (new DebitBatchService())->createUserBatch($user, $invoice, $debitBatchEntity);
                    (new DebitBatchService())->updateBatchTotal($debitBatchEntity);
                }
            }

            $accountNumber = $paymentDetails['account_number'] ?? null;
            $accountHolderName = $paymentDetails['account_holder_name'] ?? null;
            $bank = isset($paymentDetails['bank_id']) ? Bank::find($paymentDetails['bank_id']) : null;
            $accountType = isset($paymentDetails['account_type_id']) ? AccountType::from($paymentDetails['account_type_id']) : null;
            $branchCode = $paymentDetails['branch_code'] ?? null;
            $iban = isset($paymentDetails['iban']) ? Str::replace(' ', '', $paymentDetails['iban']) : null;
            $bic = isset($paymentDetails['bic']) ? Str::replace(' ', '', $paymentDetails['bic']) : null;
            $address = $paymentDetails['address'] ?? null;

            // update the user banking details
            (new TenantUserService())->createOrUpdateUserBankingDetails(
                $tenantUser,
                $debitDay,
                $accountNumber,
                $accountHolderName,
                $accountType,
                $bank,
                $branchCode,
                $iban,
                $bic,
                $address
            );
        }

        $invoice?->update([
            'discriminator' => InvoiceDiscriminator::SIGN_UP_INVOICE,
        ]);

        return $invoice;
    }

    public function generateTopUpInvoiceForUserPackage(UserPackage $userPackage, LocationUser $userFacilityMembership, $sessionAmount, \Illuminate\Support\Carbon $dueOn, ?bool $isSessionsReleased = false): UserInvoice
    {
        $box = $userFacilityMembership->location->tenant;

        // set the invoice dates
        $date = $dueOn->clone();
        $date->modify('first day of this month');
        $endDate = $date->clone();
        $endDate->modify('last day of this month');

        // Create invoice
        $invoice = UserInvoice::create([
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($userFacilityMembership->location),
            'user_to_facility_id' => $userFacilityMembership->getKey(),
            'type' => InvoiceType::INVOICE->value,
            'description' => 'Sessions top-up invoice',
            'status' => InvoiceStatus::UNPAID->value,
            'currency' => $box->memberCurrency->code,
            'user_to_package_id' => $userPackage->getKey(),
            'amount' => $userPackage->package->package_topup_price * $sessionAmount,
            'discriminator' => InvoiceDiscriminator::TOPUP_INVOICE->value,
            'due_on' => $dueOn,
            'period_start' => $date,
            'period_end' => $endDate,
        ]);

        // Create invoice line item
        UserInvoiceItem::create([
            'invoice_id' => $invoice->getKey(),
            'discriminator' => 'membership',
            'description' => 'Membership top-up fee: '.$userPackage->package->package_name,
            'quantity' => $sessionAmount,
            'UnitPrice' => $userPackage->package->package_topup_price,
            'amount' => $userPackage->package->package_topup_price * $sessionAmount,
        ]);

        $topUp = FinanceTopUp::create([
            'created_by_id' => $userPackage->user_id,
            'invoice_id' => $invoice->getKey(),
            'number_of_sessions' => $sessionAmount,
            'user_to_package_id' => $userPackage->getKey(),
        ]);

        if ($isSessionsReleased) {
            $topUp->setAttribute('released', true);
            $topUp->save();
        }

        return $invoice;
    }

    public function createPaymentForInvoice(UserInvoice $invoice, $invoicePaymentType, ?float $amount = null, ?Carbon $paymentDate = null, ?string $reference = null, ?array $tagIds = null): UserInvoicePayment
    {
        // Create payment for invoice
        $payment = UserInvoicePayment::create([
            'invoice_id' => $invoice->getKey(),
            'amount' => $amount ?: $invoice->amount,
            'currency' => $invoice->currency,
            'date_time' => $paymentDate ?: now(),
            'reference' => $reference,
            'type' => $invoicePaymentType,
            'user_to_facility_id' => $invoice->user_to_facility_id,
        ]);

        $invoice->update([
            'status' => $invoice->outstanding_amount <= 0 ? InvoiceStatus::PAID : InvoiceStatus::UNPAID,
        ]);

        $isValidInvoice = $invoice->isPaid() &&
            ($invoice->tenantUser() || $invoice->discriminator === InvoiceDiscriminator::DROP_IN_INVOICE);

        if ($isValidInvoice) {
            if ($invoice->discriminator === InvoiceDiscriminator::TOPUP_INVOICE) {
                $topUp = FinanceTopUp::query()
                    ->where('invoice_id', '=', $invoice->getKey())
                    ->where('released', '=', false)
                    ->where('deleted', '=', false)
                    ->first();

                if ($topUp) {
                    $totalSessions = $topUp->userPackage->sessions_available + $topUp->number_of_sessions;
                    $topUp->userPackage()->update(['sessions_available' => $totalSessions]);
                    $topUp->released = true;
                    $topUp->save();
                }
            } elseif ($invoice->discriminator === InvoiceDiscriminator::SIGN_UP_INVOICE) {
                $locationUser = $invoice->userLocation;
                (new WidgetService())->completeSignUp(
                    $locationUser->user->getKey(),
                    $locationUser->location->tenant->getKey(),
                    true
                );
            } elseif ($invoice->discriminator === InvoiceDiscriminator::DROP_IN_INVOICE) {
                (new WidgetService())->completeDropIn($invoice, true);
            } elseif ($invoice->userPackage()->exists() &&
                $invoice->userPackage->package->type === PackageType::LIMITED &&
                in_array($invoice->discriminator, [
                    InvoiceDiscriminator::BUY_PACKAGE_INVOICE,
                    InvoiceDiscriminator::ALLOCATED_PACKAGE_INVOICE,
                ])
            ) {
                $totalSessions = $invoice->userPackage->sessions_available + $invoice->userPackage->package->limit;
                $invoice->userPackage()->update(['sessions_available' => $totalSessions]);
            }
        }

        if ($tagIds) {
            (new TagsService())->sync($tagIds, $payment, auth()->user()?->getAuthIdentifier());
        }

        return $payment;
    }

    /**
     * Undocumented function
     *
     * @param [type] $status
     *
     * @throws NoActivePackagesException
     */
    public function generateMembershipInvoice(User $user, Location $location, Carbon $dueOn, Carbon $generationDate, InvoiceStatus $status, ?DebitBatch $debitBatch = null): UserInvoice
    {
        $activePackages = UserPackage::query()
            ->active()
            ->with('package')
            ->where('user_id', $user->getAuthIdentifier())
            ->where('packages.box_id', $location->tenant_id)
            ->get();

        $locationUser = LocationUser::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('box_facility_id', $location->getKey())
            ->first();

        if ($activePackages->count() === 0) {
            throw new NoActivePackagesException();
        }

        $financeService = new FinanceService();

        $packageTotal = $financeService->getPackageTotal($location->tenant_id, $user->getAuthIdentifier(), $activePackages);

        $discountEntry = $financeService->getDiscountArray($locationUser, $packageTotal);

        $invoice = UserInvoice::create([
            'code' => $financeService->generateInvoiceNumberForFacility($locationUser->location),
            'user_to_facility_id' => $locationUser->getKey(),
            'type' => InvoiceType::INVOICE,
            'description' => 'Membership invoice (cash)',
            'status' => $status,
            'currency' => $location->tenant->memberCurrency->code,
            'due_on' => $dueOn,
            'period_start' => $dueOn->copy()->addMonthNoOverflow()->firstOfMonth(),
            'period_end' => $generationDate->copy()->lastOfMonth(),
            'amount' => $packageTotal + Arr::get($discountEntry, 'amount', 0),
            'discriminator' => InvoiceDiscriminator::INVOICE,
        ]);

        $invoice->invoiceItems()->createMany(array_merge(
            $activePackages->map(function ($userPackage) {
                return [
                    'user_to_package_id' => $userPackage->getKey(),
                    'discriminator' => InvoiceItemDiscriminator::MEMBERSHIP,
                    'description' => $userPackage->package->name,
                    'quantity' => 1,
                    'unitPrice' => $userPackage->package->getPrice(),
                    'amount' => $userPackage->package->getPrice(),
                ];
            })->toArray(),
            ! empty($discountEntry) ? [$discountEntry] : []
        ));

        return $invoice;
    }

    public function sendUserInvoice(UserInvoice $invoice, Location $location, ?string $email = null, ?string $name = null): void
    {
        $userLocation = $invoice->userLocation;

        if ($userLocation) {
            $member = $userLocation->user;

            $memberName = $member->name;
            $memberSurname = $member->surname;
            $recipient = $member;
        } else {
            $memberName = $name;
            $memberSurname = '';
            $recipient = $email;
        }

        $invoice->loadMissing(['location', 'userLocation.user']);

        $fileName = 'invoice_'.$invoice->code.'.pdf';

        $filePath = $this->generateUserInvoicePDF(
            invoice: $invoice,
            openingBalance: 0,
            filename: $fileName,
        );

        (new CrmService)->createScheduledEmailForNotification(
            tenantOrLocation: $invoice->invoice_location,
            context: 'send_invoice',
            recipient: $recipient,
            data: [
                'member_name' => $memberName,
                'member_surname' => $memberSurname,
            ],
            attach: [
                'disk' => 'tmp',
                'path' => $filePath,
                'filename' => $fileName,
            ]
        );

        $invoice->update([
            'sent_on' => now(),
        ]);

    }

    public function generateUserInvoicePDF(UserInvoice $invoice, $openingBalance, string $filename): string
    {
        $path = 'user-invoices/'.$filename;
        $resizedImagePath = null;

        $url = $invoice->invoice_location->logo_url;
        if (! empty($url)) {
            // Check if the file exists and is accessible
            $headers = get_headers($url);
            if ($headers && strpos($headers[0], '200') !== false) {
                $ext = pathinfo($url, PATHINFO_EXTENSION);
                if (empty($ext)) {
                    $ext = 'jpg'; // Default extension if none is found
                }

                $resizedImagePath = public_path().'/'.uniqid(rand(), true).'.'.$ext;

                $image = new \Imagick();
                $image->readImage($url);
                $image->scaleImage(1000, 0);
                $image->writeImage($resizedImagePath);
                $image->clear();
            }
        }

        Pdf::loadView('pdf.user-invoice', [
            'openingBalance' => $openingBalance,
            'invoice' => $invoice->loadMissing('invoiceItems'),
            'location' => $invoice->invoice_location,
            'isDownload' => true,
            'image' => $resizedImagePath,
        ])->save($path, 'tmp');

        if ($resizedImagePath && file_exists($resizedImagePath)) {
            unlink($resizedImagePath);
        }

        return $path;
    }

    public function generateFacilityInvoice(LocationInvoice $invoice, string $filename): string
    {
        $path = 'finance-invoices/'.$filename;

        Pdf::loadView('pdf.user-invoice', [
            'invoice' => $invoice,
        ])->save($path, 'tmp');

        return $path;
    }

    public function getInvoices(Request $paramFetcher, TenantUser $tenantUser, $query = false): QueryBuilder|LengthAwarePaginator
    {
        $isLocationAdmin = $tenantUser->isLocationAdmin();
        $type = $paramFetcher->input('filter.type');
        $paymentType = $paramFetcher->input('filter.payment_type');

        // Invoice query builder
        if ($type === 'nonMemberInvoices') {
            $queryBuilder = $this->getNonMemberInvoicesQueryBuilder($paramFetcher);

        } elseif ($type === 'leadMemberInvoices') {
            $queryBuilder = $this->getLeadMemberInvoicesQueryBuilder($paramFetcher);
        } else {
            $packageId = $paramFetcher->input('filter.package_id');
            $queryBuilder = $this->getUserInvoicesQueryBuilder($paramFetcher);

            if ($paymentType == 'debitOrderInvoices') {
                $queryBuilder->join('user_to_batch as ub', 'ub.invoice_id', '=', 'finance_invoices.invoice_id');
            } elseif ($paymentType == 'otherInvoices') {
                $queryBuilder
                    ->leftJoin('user_to_batch as ub', 'ub.invoice_id', '=', 'finance_invoices.invoice_id')
                    ->whereNull('ub.invoice_id');
            }

            if ($packageId) {
                $queryBuilder
                    ->join('finance_invoice_items as it', 'it.invoice_id', 'finance_invoices.invoice_id')
                    ->leftJoin('user_to_package as invoiceUserPackage', 'invoiceUserPackage.user_to_package_id', 'finance_invoices.user_to_package_id')
                    ->leftJoin('user_to_package as invoiceItemUserPackage', 'invoiceItemUserPackage.user_to_package_id', 'it.user_to_package_id')
                    ->where(function ($query) use ($packageId) {
                        $query->where('invoiceUserPackage.package_id', '=', $packageId)
                            ->orWhere('invoiceItemUserPackage.package_id', '=', $packageId);
                    })
                    ->where('it.deleted', '=', false)
                    ->whereIn('it.discriminator', ['membership', 'discount', 'prorate']);
            }
        }

        if (! $paramFetcher->input('filter.location_id')) {
            if ($isLocationAdmin) {
                $locationIds = [(new TenantUserService())->getLocationUserByTenant($tenantUser->user, $tenantUser->tenant)?->location_id];
            } else {
                $locationIds = (new AccessPrivilegeService())->getAllowedLocationIds($tenantUser);
            }

            $queryBuilder->whereIn('f.box_facility_id', $locationIds);
        }

        if ($query) {
            return $queryBuilder->withoutGlobalScopes()->newQuery()->with(['location', 'invoiceItems']);
        }

        return $queryBuilder->withoutGlobalScopes()->newQuery()->with(['location', 'invoiceItems'])->_paginate();
    }

    public function getNonMemberInvoicesQueryBuilder(Request $request): QueryBuilder
    {
        return QueryBuilder::for(UserInvoice::class)
            ->select('finance_invoices.*')
            ->join('box_facility as f', 'finance_invoices.box_facility_id', '=', 'f.box_facility_id')
            ->whereNull('finance_invoices.user_to_facility_id')
            ->whereNull('finance_invoices.lead_member_id')
            ->where('finance_invoices.deleted', '=', false)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'f.box_id'),
                AllowedFilter::exact('location_id', 'f.box_facility_id'),
                AllowedFilter::exact('payment_type', 'finance_invoices.type'),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $start = \Illuminate\Support\Carbon::parse($value[0])->setTime(0, 0)->toDateTimeString();
                    $end = \Illuminate\Support\Carbon::parse($value[1])->setTime(23, 59, 59)->toDateTimeString();

                    $query->whereBetween('finance_invoices.due_on', [$start, $end]);

                }),
                AllowedFilter::callback('sent_status', function (Builder $query, $value) {
                    if ($value == 'sent') {
                        $query->whereNotNull('finance_invoices.sent_on');
                    } elseif ('unsent') {
                        $query->whereNull('finance_invoices.sent_on');
                    }
                }),
                AllowedFilter::exact('status', 'finance_invoices.status'),
                AllowedFilter::callback('search', function (Builder $query, $value) {

                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }

                    $query->where(function ($query) use ($value) {
                        $query->where('finance_invoices.non_member_name', 'LIKE', '%'.$value.'%')
                            ->orWhere('finance_invoices.non_member_email', 'LIKE', '%'.$value.'%')
                            ->orWhere('finance_invoices.description', 'LIKE', '%'.$value.'%')
                            ->orWhere('finance_invoices.code', 'LIKE', '%'.$value.'%');
                    });
                }),
            ])
            ->allowedSorts([
                AllowedSort::field('due_on', 'finance_invoices.due_on'),
                AllowedSort::field('code', 'finance_invoices.code'),
                AllowedSort::field('user_name', 'finance_invoices.non_member_name'),
                AllowedSort::field('description', 'finance_invoices.description'),
                AllowedSort::field('sent_on', 'finance_invoices.sent_on'),
                AllowedSort::field('amount', 'finance_invoices.amount'),
                AllowedSort::field('status', 'finance_invoices.status'),
            ])
            ->defaultSort('-finance_invoices.due_on')
            ->distinct();
    }

    public function getLeadMemberInvoicesQueryBuilder(Request $request): QueryBuilder
    {
        return QueryBuilder::for(UserInvoice::class)
            ->select('finance_invoices.*')
            ->join('user_to_facility', 'user_to_facility.user_to_facility_id', '=', 'finance_invoices.user_to_facility_id')
            ->join('box_facility as f', 'user_to_facility.box_facility_id', '=', 'f.box_facility_id')
            ->join('lead_members', 'user_to_facility.lead_member_id', '=', 'lead_members.member_id')
            ->join('users', 'lead_members.user_id', '=', 'users.user_id')
            ->where('users.deleted', 0)
            ->where('finance_invoices.deleted', '=', false)
            ->where('finance_invoices.type', '=', InvoiceType::INVOICE)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'f.box_id'),
                AllowedFilter::exact('location_id', 'f.box_facility_id'),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $start = \Illuminate\Support\Carbon::parse($value[0])->setTime(0, 0)->toDateTimeString();
                    $end = \Illuminate\Support\Carbon::parse($value[1])->setTime(23, 59, 59)->toDateTimeString();

                    $query->whereBetween('finance_invoices.due_on', [$start, $end]);
                }),
                AllowedFilter::callback('sent_status', function (Builder $query, $value) {
                    if ($value == 'sent') {
                        $query->whereNotNull('finance_invoices.sent_on');
                    } elseif ('unsent') {
                        $query->whereNull('finance_invoices.sent_on');
                    }
                }),
                AllowedFilter::exact('status', 'finance_invoices.status'),
                AllowedFilter::callback('search', function (Builder $query, $value) {

                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }

                    $query->where(function ($query) use ($value) {
                        $query
                            ->where('users.name', 'LIKE', '%'.$value.'%')
                            ->orWhere('users.surname', 'LIKE', '%'.$value.'%')
                            ->orWhereRaw("CONCAT( CONCAT(users.name, ' '),  users.surname) LIKE ?", '%'.$value.'%')
                            ->orWhere('users.email', 'LIKE', '%'.$value.'%')
                            ->orWhere('finance_invoices.description', 'LIKE', '%'.$value.'%')
                            ->orWhere('finance_invoices.code', 'LIKE', '%'.$value.'%');
                    });
                }
                ),
            ])
            ->allowedSorts([
                AllowedSort::field('due_on', 'finance_invoices.due_on'),
                AllowedSort::field('code', 'finance_invoices.code'),
                AllowedSort::callback('users.name', function (Builder $query, $descending) {
                    $query->orderBy('users.name', $descending ? SortDirection::DESCENDING : SortDirection::ASCENDING)
                        ->orderBy('users.surname', $descending ? SortDirection::DESCENDING : SortDirection::ASCENDING);
                }),
                AllowedSort::field('description', 'finance_invoices.description'),
                AllowedSort::field('sent_on', 'finance_invoices.sent_on'),
                AllowedSort::field('amount', 'finance_invoices.amount'),
                AllowedSort::field('status', 'finance_invoices.status'),
            ])
            ->defaultSort('-finance_invoices.due_on')
            ->distinct();
    }

    public function getUserInvoicesQueryBuilder(Request $request): QueryBuilder
    {
        return QueryBuilder::for(UserInvoice::class)
            ->select('finance_invoices.*')
            ->join('user_to_facility as fm', 'fm.user_to_facility_id', '=', 'finance_invoices.user_to_facility_id')
            ->join('box_facility as f', 'fm.box_facility_id', '=', 'f.box_facility_id')
            ->join('users as u', 'fm.user_id', '=', 'u.user_id')
            ->whereNull('finance_invoices.lead_member_id')
            ->where('u.deleted', 0)
            ->where('finance_invoices.deleted', '=', false)
            ->where('finance_invoices.type', '=', InvoiceType::INVOICE->value)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'f.box_id'),
                AllowedFilter::exact('location_id', 'f.box_facility_id'),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $start = \Illuminate\Support\Carbon::parse($value[0])->setTime(0, 0)->toDateTimeString();
                    $end = \Illuminate\Support\Carbon::parse($value[1])->setTime(23, 59, 59)->toDateTimeString();

                    $query->whereBetween('finance_invoices.due_on', [$start, $end]);

                }),
                AllowedFilter::callback('sent_status', function (Builder $query, $value) {
                    if ($value == 'sent') {
                        $query->whereNotNull('finance_invoices.sent_on');
                    } elseif ($value == 'unsent') {
                        $query->whereNull('finance_invoices.sent_on');
                    }
                }),
                AllowedFilter::exact('status', 'finance_invoices.status'),
                AllowedFilter::callback(
                    'search',
                    function (Builder $query, $value) {

                        if (is_array($value)) {
                            $value = implode(',', $value);
                        }

                        $query->where(function ($query) use ($value) {
                            $query
                                ->where('u.name', 'LIKE', '%'.$value.'%')
                                ->orWhere('u.surname', 'LIKE', '%'.$value.'%')
                                ->orWhereRaw("CONCAT( CONCAT(u.name, ' '),  u.surname) LIKE ?", '%'.$value.'%')
                                ->orWhere('u.email', 'LIKE', '%'.$value.'%')
                                ->orWhere('finance_invoices.description', 'LIKE', '%'.$value.'%')
                                ->orWhere('finance_invoices.code', 'LIKE', '%'.$value.'%');
                        }
                        );
                    }
                ),
            ])
            ->allowedSorts([
                AllowedSort::field('due_on', 'finance_invoices.due_on'),
                AllowedSort::field('code', 'finance_invoices.code'),
                AllowedSort::callback('user_name', function (Builder $query, $descending) {
                    $query->orderBy('u.name', $descending ? SortDirection::DESCENDING : SortDirection::ASCENDING)
                        ->orderBy('u.surname', $descending ? SortDirection::DESCENDING : SortDirection::ASCENDING);
                }),
                AllowedSort::field('description', 'finance_invoices.description'),
                AllowedSort::field('sent_on', 'finance_invoices.sent_on'),
                AllowedSort::field('amount', 'finance_invoices.amount'),
                AllowedSort::field('status', 'finance_invoices.status'),
            ])
            ->allowedIncludes([
                AllowedInclude::relationship('user_tenant', 'userTenant'),
                AllowedInclude::relationship('payments', 'payments'),
            ])
            ->defaultSort('-finance_invoices.due_on')
            ->distinct();
    }

    public function getInvoiceItemsForInvoice(UserInvoice $invoice): Collection|array
    {
        return UserInvoiceItem::query()
            ->where('invoice_id', '=', $invoice->getKey())
            ->where('deleted', '=', false)
            ->get();
    }

    public function markInvoiceAsDeleted(UserInvoice $invoice): UserInvoice|string
    {
        if ($invoice->userBatch instanceof UserBatch) {
            // Check if batch has been processed
            if ($invoice->userBatch->debitBatch->is_processed) {
                abort(400, 'Invoice cannot be deleted because the debit batch has already been processed!');
            }

            // Disable user to batch record
            $invoice->userBatch->setAttribute('is_active', false);

            // Update debit batch total
            $total = (new DebitBatchService())->getTotalForDebitBatch($invoice->userBatch->debitBatch);
            $invoice->userBatch->debitBatch->setAttribute('debit_batch_total', $total);
        } else {
            // Check if invoice was for an upfront paying member
            if ($invoice->upfront_user_id != null) {
                $user = $invoice->locationUser->user;
                $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($user, $invoice->locationUser->tenant);

                if ($userBoxMembership->user_debit_status_id === UserDebitStatus::UP_FRONT_PAYMENT) {
                    $userBoxMembership->setAttribute('user_debit_status_id', UserDebitStatus::UP_FRONT_PAYMENT->value);
                }
            }

            if ($invoice->type === InvoiceType::CREDIT_NOTE && $invoice->parentInvoice) {
                $parentInvoice = $invoice->parentInvoice;
                $parentInvoice->setAttribute('status', InvoiceStatus::UNPAID->value);
            }
        }

        $invoice->push();

        return (new DebitBatchService())->markAsDeleted($invoice);
    }

    public function getInvoicesForUserBeforeDate(User $user, Tenant $tenant, string $type, $date): Collection|array
    {
        return UserInvoice::query()
            ->from('finance_invoices')
            ->join('user_to_facility', 'user_to_facility.user_to_facility_id', '=', 'finance_invoices.user_to_facility_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'user_to_facility.box_facility_id')
            ->where('user_to_facility.user_id', '=', $user->getKey())
            ->where('box_facility.box_id', '=', $tenant->getKey())
            ->where('box_facility.is_active', '=', true)
            ->where('finance_invoices.type', '=', $type)
            ->where('finance_invoices.due_on', '<', $date)
            ->where('finance_invoices.deleted', '=', false)
            ->orderBy('finance_invoices.due_on')
            ->distinct()
            ->get();
    }

    public function getInvoiceBalance($invoices, $creditNotes): string
    {
        $totalPayments = '0';
        $totalOwing = '0';
        $totalCredit = '0';

        /** @var UserInvoice $invoice */
        foreach ($invoices as $invoice) {
            if ($invoice->userBatch && ! $invoice->userBatch->is_active) {
                continue;
            }

            $totalOwing = bcadd($totalOwing, (string) $invoice->amount, 2);

            /** @var UserInvoicePayment $payment */
            foreach ($invoice->payments as $payment) {
                if ($payment->deleted != 1) {
                    $totalPayments = bcadd($totalPayments, (string) $payment->amount, 2);
                }
            }
        }

        /** @var UserInvoice $creditNote */
        foreach ($creditNotes as $creditNote) {
            $totalCredit = bcadd($totalCredit, (string) $creditNote->amount, 2);
        }

        $balanceAfterCredit = bcsub($totalOwing, $totalCredit, 2);

        return bcsub($balanceAfterCredit, $totalPayments, 2);
    }

    public function createCreditNoteForInvoice(UserInvoice $invoice): UserInvoice
    {
        $now = now();
        $creditNote = new UserInvoice();
        $invoice->update([
            'status' => InvoiceStatus::CREDITED,
        ]);

        $creditNote->setAttribute('description', 'Credit note issued for this invoice: '.$invoice->code);
        $creditNote->setAttribute('due_on', $now);
        $creditNote->setAttribute('period_start', $now);
        $creditNote->setAttribute('period_end', $now);
        $creditNote->setAttribute('amount', $invoice->amount);
        $creditNote->setAttribute('code', 'CR'.uniqid());
        $creditNote->setAttribute('type', InvoiceType::CREDIT_NOTE->value);
        $creditNote->setAttribute('status', InvoiceStatus::ISSUED->value);
        $creditNote->setAttribute('currency', $invoice->currency);
        $creditNote->setAttribute('user_to_facility_id', $invoice->locationUser?->getKey());
        $creditNote->setAttribute('parent_invoice_id', $invoice->getKey());
        $creditNote->save();

        // Delete all the payments linked to this invoice
        /** @var UserInvoicePayment $invoicePayment */
        foreach ($invoice->getActivePayments() as $invoicePayment) {
            $invoicePayment->update(['deleted' => 1]);
        }

        return $creditNote;
    }

    public function getUserLatestUpfrontInvoice(User $user, Tenant $box): ?object
    {
        return UserInvoice::query()
            ->from('finance_invoices', 'i')
            ->join('finance_invoice_items as it', 'it.invoice_id', '=', 'i.invoice_id')
            ->join('user_to_facility as fm', 'fm.user_to_facility_id', '=', 'i.user_to_facility_id')
            ->join('box_facility as f', 'f.box_facility_id', '=', 'fm.box_facility_id')
            ->where('fm.user_id', '=', $user->getKey())
            ->where('f.box_id', '=', $box->getKey())
            ->where('i.upfront_user_id', '=', $user->getKey())
            ->where('i.type', '=', 'invoice')
            ->where('i.status', '!=', 'credited')
            ->where('it.discriminator', '=', 'membership')
            ->where('i.deleted', '=', false)
            ->where('it.deleted', '=', false)
            ->where('i.period_end', '>', today()->toDateString())
            ->limit(1)
            ->orderBy('i.created_on', 'desc')
            ->first();
    }

    public function generateUserInvoice(User $member, Tenant $box, DateTime $dueDate): ?object
    {
        $locationUser = (new TenantUserService())->getLocationUserByTenant($member, $box);
        $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($member, $box);

        $boxFacility = $locationUser?->location;

        if (! $boxFacility instanceof Location) {
            return null;
        }

        $userDiscount = $locationUser->activeDiscounts()->orderBy('facility_membership_discount_id', 'desc')->first();

        $specialRate = SpecialRate::for($member, $box);

        // Create invoice
        $invoice = UserInvoice::query()->create([
            'user_to_facility_id' => $locationUser->getKey(),
            'type' => InvoiceType::INVOICE,
            'description' => $userTenant->type == UserType::LEAD_MEMBER ? $boxFacility->name.': Lead invoice' : 'Membership invoice',
            'status' => InvoiceStatus::UNPAID,
            'due_on' => $dueDate,
            'period_start' => $dueDate,
            'period_end' => $dueDate,
            'currency' => $box->memberCurrency->code,
            'created_by_id' => $member->getKey(),
            'user_to_package_id' => (new UserPackageService())->getActiveUserPackageForTenant($member, $box, $dueDate)->getKey(),
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($boxFacility),
            'amount' => $this->calculateMemberFee($member, $box, $dueDate, $specialRate, $userDiscount),
        ]);

        /** @var UserPackage $userPackage */
        foreach ((new UserPackageService())->getActiveUserPackagesForTenant($member, $box, $dueDate) as $userPackage) {
            UserInvoiceItem::query()->create([
                'invoice_id' => $invoice->getKey(),
                'discriminator' => 'membership',
                'description' => $userTenant->type == UserType::LEAD_MEMBER ? $boxFacility->name.': Lead invoice' : $userPackage->package->package_name,
                'quantity' => 1,
                'unitPrice' => $userPackage->package->package_price,
                'amount' => $userPackage->package->package_price,
            ]);
        }

        if ($specialRate instanceof SpecialRate && $specialRate->amount > 0) {
            $specialRateDiscount = (new UserPackageService())->getActiveUserPackagesTotalForTenant($member, $box, $dueDate) - $specialRate->amount;

            UserInvoiceItem::query()->create([
                'invoice_id' => $invoice->getKey(),
                'discriminator' => 'special_discount',
                'description' => 'Special rate discount',
                'quantity' => 1,
                'unitPrice' => -$specialRateDiscount,
                'amount' => -$specialRateDiscount,
            ]);

        } // then discount amount
        elseif ($userDiscount instanceof LocationUserDiscount) {
            $discount = $userDiscount->discount;

            if ($discount->type == 'fixed') {
                $discountAmount = $discount->amount;
            } else {
                $discountAmount = (new UserPackageService())->getActiveUserPackagesTotalForTenant($member, $box, $dueDate) * ($discount->amount / 100);
            }

            UserInvoiceItem::query()->create([
                'invoice_id' => $invoice->getKey(),
                'discriminator' => 'discount',
                'description' => 'Discount : '.$userDiscount->discount->name,
                'quantity' => 1,
                'unitPrice' => -$discountAmount,
                'amount' => -$discountAmount,
            ]);
        }

        return $invoice;
    }

    public function generateCashInvoiceForUser(LocationUser $locationUser, \Illuminate\Support\Carbon $dueOn, \Illuminate\Support\Carbon $generationDate)
    {
        $user = $locationUser->user;
        $location = $locationUser->location;
        $tenant = $location->tenant;

        $userDiscount = $locationUser->activeDiscounts()->orderBy('facility_membership_discount_id', 'desc')->first();

        $specialRate = SpecialRate::for($user, $tenant);

        $date = $dueOn->clone();
        $date->modify('first day of next month');
        $endDate = $date->clone();
        $endDate->modify('last day of this month');

        // Create invoice
        $invoice = UserInvoice::query()->create([
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($location),
            'user_to_facility_id' => $locationUser->getKey(),
            'type' => InvoiceType::INVOICE,
            'description' => 'Membership invoice (cash)',
            'status' => InvoiceStatus::UNPAID,
            'due_on' => $dueOn,
            'period_start' => $date,
            'period_end' => $endDate,
            'currency' => $tenant->memberCurrency->code,
            'amount' => $this->calculateMemberFee($user, $tenant, $dueOn, $specialRate, $userDiscount, true),
        ]);

        $activeUserPackages = (new UserPackageService())->getActiveUserPackagesExcludingLimitedPackagesForTenant($user, $tenant, $dueOn);

        $invoice->invoiceItems()->createMany(
            $activeUserPackages->map(function ($userPackage) {
                return [
                    'user_to_package_id' => $userPackage->getKey(),
                    'discriminator' => InvoiceItemDiscriminator::MEMBERSHIP,
                    'description' => $userPackage->package->name,
                    'quantity' => 1,
                    'unitPrice' => $userPackage->package->price,
                    'amount' => $userPackage->package->price,
                ];
            })->toArray()
        );

        if ($specialRate instanceof SpecialRate && $specialRate->amount > 0) {
            $specialRateDiscount = $activeUserPackages->sum('package.price') - $specialRate->amount;

            $invoice->invoiceItems()->create([
                'invoice_id' => $invoice->getKey(),
                'discriminator' => 'special_discount',
                'description' => 'Special rate discount',
                'quantity' => 1,
                'unitPrice' => -$specialRateDiscount,
                'amount' => -$specialRateDiscount,
            ]);

        } // then discount amount
        elseif ($userDiscount instanceof LocationUserDiscount) {
            $discount = $userDiscount->discount;

            if ($discount->type == 'fixed') {
                $discountAmount = $discount->amount;
            } else {
                $discountAmount = $activeUserPackages->sum('package.price') * ($discount->amount / 100);
            }

            $invoice->invoiceItems()->create([
                'invoice_id' => $invoice->getKey(),
                'discriminator' => 'discount',
                'description' => 'Discount : '.$userDiscount->discount->name,
                'quantity' => 1,
                'unitPrice' => -$discountAmount,
                'amount' => -$discountAmount,
            ]);
        }

        return $invoice;
    }

    private function calculateMemberFee(User $user, Tenant $box, DateTime $date, ?SpecialRate $specialRate = null, ?LocationUserDiscount $membershipDiscount = null, ?bool $isExcludeLimitedPackages = false): float
    {
        if ($specialRate) {
            return $specialRate->amount;
        }

        $packagePrice = 0;

        $userPackages = $isExcludeLimitedPackages ? (new UserPackageService())->getActiveUserPackagesExcludingLimitedPackagesForTenant($user, $box, $date) : (new UserPackageService())->getActiveUserPackagesForTenant($user, $box, $date);

        /** @var UserPackage $package */
        foreach ($userPackages as $package) {
            $packagePrice += $package->package->price;
        }

        if ($membershipDiscount && $membershipDiscount->discount->type === 'fixed') {
            $packagePrice = $packagePrice - $membershipDiscount->discount->amount;
        } elseif ($membershipDiscount && $membershipDiscount->discount->type === 'percentage') {
            $packagePrice = $packagePrice - ((float) $membershipDiscount->discount->amount / 100 * $packagePrice);
        }

        return $packagePrice;
    }

    public function sendInvoice(UserInvoice $invoice): ?string
    {
        $errorMessage = null;

        try {
            if ($invoice->locationUser instanceof LocationUser && $invoice->locationUser->user) {
                $user = $invoice->locationUser->user;
                $name = $user->name;
                $surname = $user->surname;
                $recipient = $user;
                $location = $invoice->locationUser->location;
            } elseif ($invoice->locationUser instanceof LocationUser && $invoice->locationUser->leadMember) {
                $leadMember = $invoice->locationUser->leadMember;

                $name = $leadMember->user?->name;
                $surname = $leadMember->user?->surname;
                $recipient = $leadMember->user;
                $location = $invoice->locationUser->location;
            } else {
                $name = $invoice->non_member_name;
                $surname = '';
                $recipient = $invoice->non_member_email;
                $location = $invoice->location;
            }

            $openingBalance = 0;

            if ($invoice->locationUser instanceof LocationUser && $invoice->locationUser->user) {
                // Invoices before start date
                $beforeDate = clone $invoice->due_on;
                $beforeDate->sub(new \DateInterval('P1D'));
                $invoicesBeforeDate = (new InvoiceService())->getInvoicesForUserBeforeDate($invoice->locationUser->user, $location->tenant, InvoiceType::INVOICE->value, $beforeDate);
                $creditNotesBeforeDate = (new InvoiceService())->getInvoicesForUserBeforeDate($invoice->locationUser->user, $location->tenant, InvoiceType::CREDIT_NOTE->value, $beforeDate);

                $openingBalance = $this->getInvoiceBalance($invoicesBeforeDate, $creditNotesBeforeDate);
            }

            $attachment = $this->generateUserInvoicePDF($invoice, $openingBalance, $invoice->code.'_'.$invoice->getKey().'.pdf');

            $invoice->update(['sent_on' => new DateTime()]);

            (new CrmService())->createScheduledEmailForNotification(
                tenantOrLocation: $location,
                context: 'send_invoice',
                recipient: $recipient,
                data: ['member_name' => $name, 'member_surname' => $surname],
                attach: ['disk' => 'tmp', 'path' => $attachment, 'filename' => $invoice->code.'_'.$invoice->getKey().'.pdf']
            );

        } catch (\Exception $exception) {
            Log::info($exception->getMessage());
        }

        return $errorMessage;
    }

    public function getUsersLastMembershipInvoiceForTenant(User $user, Tenant $box, $start = null, $end = null)
    {
        return UserInvoice::query()
            ->from('finance_invoices', 'i')
            ->join('user_to_facility as fm', 'fm.user_to_facility_id', '=', 'i.user_to_facility_id')
            ->join('box_facility as f', 'f.box_facility_id', '=', 'fm.box_facility_id')
            ->join('finance_invoice_items as it', 'i.invoice_id', '=', 'it.invoice_id')
            ->where('fm.user_id', '=', $user->getKey())
            ->where('f.box_id', '=', $box->getKey())
            ->where('i.type', '=', 'invoice')
            ->where('it.discriminator', '=', 'membership')
            ->where('i.deleted', '=', false)
            ->where('it.deleted', '=', false)
            ->when($start && $end, function ($q) use ($start, $end) {
                return $q->whereBetween('i.due_on', [$start, $end]);
            })
            ->limit(1)
            ->orderBy('i.created_on', 'desc')
            ->first();
    }

    public function getUserStatement(User $user, Tenant $box, $startDate, $endDate): array
    {
        // Invoices and credit notes together
        $invoicesAndCreditNotes = $this->getInvoicesForUserStatement($user, $box, $startDate, $endDate);

        // Current invoices for date period
        $currentInvoices = $this->getInvoicesForUser($user, $box, InvoiceType::INVOICE->value, $startDate, $endDate);
        $currentCreditNotes = $this->getInvoicesForUser($user, $box, InvoiceType::CREDIT_NOTE->value, $startDate, $endDate);

        // Invoices before start date
        $invoicesBeforeDate = $this->getInvoicesForUserBeforeDate($user, $box, InvoiceType::INVOICE->value, $startDate);
        $creditNotesBeforeDate = $this->getInvoicesForUserBeforeDate($user, $box, InvoiceType::CREDIT_NOTE->value, $startDate);

        $total = 0;
        $openingBalance = $this->getInvoiceBalance($invoicesBeforeDate, $creditNotesBeforeDate);
        $transactions = array_values($this->getTransactions($invoicesAndCreditNotes, $openingBalance));
        $currentBalance = $this->getInvoiceBalance($currentInvoices, $currentCreditNotes);
        $outstandingBalance = bcadd($currentBalance, $openingBalance, 2);

        // Get total
        foreach ($transactions as $transaction) {
            if ($transaction['type'] === InvoiceType::INVOICE->value) {
                $total = $total + $transaction['invoice_amount'];
            } elseif ($transaction['type'] === InvoiceType::CREDIT_NOTE->value) {
                $total = $total - $transaction['invoice_amount'];
            } elseif ($transaction['type'] === 'payment') {
                $total = $total - $transaction['payment_amount'];
            }
        }

        return [
            'currencyCode' => $box->memberCurrency->code,
            'openingBalance' => $openingBalance,
            'outstandingBalance' => $outstandingBalance,
            'total' => $total,
            'transactions' => $transactions,
        ];
    }

    public function getInvoicesForUserStatement(User $user, Tenant $box, $start = null, $end = null, $status = null, $order = null): Collection|array
    {
        $order = $order != null ? 'DESC' : 'ASC';

        $query = UserInvoice::query()
            ->select('finance_invoices.*')
            ->from('finance_invoices')
            ->join('user_to_facility as ufm', 'finance_invoices.user_to_facility_id', '=', 'ufm.user_to_facility_id')
            ->join('box_facility as bf', 'bf.box_facility_id', '=', 'ufm.box_facility_id')
            ->join('users as u', 'u.user_id', '=', 'ufm.user_id')
            ->leftJoin('user_to_batch as ub', 'ub.invoice_id', '=', 'finance_invoices.invoice_id')
            ->where('ufm.user_id', '=', $user->getKey())
            ->where('bf.box_id', '=', $box->getKey())
            ->where('bf.is_active', '=', true)
            ->where('finance_invoices.deleted', '=', false)
            ->where(function ($query) {
                $query->whereDoesntHave('userBatch')
                    ->orWhereHas('userBatch', function ($query) {
                        $query->where('is_active', '=', true);
                    });
            })
            ->orderBy('finance_invoices.due_on', $order)
            ->distinct();

        if ($start && $end) {
            $query->whereBetween('finance_invoices.due_on', [$start, $end]);
        } elseif ($end) {
            $query->where('finance_invoices.due_on', '<=', $end);
        }

        if ($status) {
            $query->where('finance_invoices.status', '=', $status);
        }

        return $query->get();
    }

    public function sendUserStatement(User $user, Tenant $tenant, $startDate, $endDate, ?string $message = null): void
    {
        $location = (new TenantUserService())->getLocationUserByTenant($user, $tenant)?->location;
        $locationLogo = $location instanceof Location ? $location->logo_url : null;

        $statement = (new InvoiceService())->getUserStatement($user, $tenant, $startDate, $endDate);
        $statement = json_decode(json_encode((object) $statement), false);

        $path = 'user-statements/'.$user->name.'_'.$user->surname.'_'.$startDate.'_'.$endDate.'_statement.pdf';

        Pdf::loadView('finance.print-statement', [
            'statement' => $statement,
            'user' => $user,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'locationLogo' => $locationLogo,
            'location' => $location,
            'userTenant' => (new TenantUserService())->getCurrentUserTenantForTenant($user, $tenant),
        ])->save($path, 'private');

        if (! $message) {
            if (! preg_match("#^\p{L}+$#u", $user->name) || ! preg_match("#^\p{L}+$#u", $user->surname)) {
                $message = "Hi athlete,\n\n".'This is your statement for '.$startDate.' - '.$endDate;
            } else {
                $message = 'Hi '.$user->full_name.",\n\n".'This is your statement for '.$startDate.' - '.$endDate;
            }
        }

        (new CrmService())->createScheduledEmailForNotification(
            tenantOrLocation: $tenant,
            context: 'send_statement',
            recipient: $user->email,
            data: [
                'message' => $message,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            attach: [
                'disk' => 'private',
                'path' => $path,
                'mimetype' => 'application/pdf',
                'delete_after_send' => true,
            ],
        );
    }

    public function getInvoicesForUser(User $user, ?Tenant $box = null, ?string $type = null, $start = null, $end = null, $status = null, $order = null): Collection|array
    {
        $order = $order != null ? 'DESC' : 'ASC';

        $query = UserInvoice::query()
            ->from('finance_invoices', 'i')
            ->join('user_to_facility as fm', 'i.user_to_facility_id', '=', 'fm.user_to_facility_id')
            ->join('box_facility as f', 'f.box_facility_id', '=', 'fm.box_facility_id')
            ->where('fm.user_id', '=', $user->getKey())
            ->where('f.is_active', '=', true)
            ->where('i.deleted', '=', false)
            ->orderBy('i.due_on', $order)
            ->distinct();

        if ($box instanceof Tenant) {
            $query->where('f.box_id', '=', $box->getKey());
        }

        if ($type) {
            $query->where('i.type', '=', $type);
        }

        if ($start && $end) {
            $query->whereBetween('i.due_on', [$start, $end]);

        } elseif ($end) {
            $query->where('i.due_on', '<=', $end);
        }

        if ($status) {
            $query->where('i.status', '=', $status);
        }

        return $query->get();
    }

    private function getTransactions($invoices, $openingBalance): array
    {
        $transactions = [];
        $rollingTotal = $openingBalance;

        /** @var UserInvoice $invoice */
        foreach ($invoices as $invoice) {
            $id = $invoice->code;

            $transactions[$id]['date'] = $invoice->due_on->format('d M y');
            $transactions[$id]['description'] = $invoice->description;
            $transactions[$id]['invoice_amount'] = $invoice->amount ?? 0;
            $transactions[$id]['type'] = $invoice->type == InvoiceType::INVOICE ? InvoiceType::INVOICE->value : InvoiceType::CREDIT_NOTE->value;

            /** @var UserInvoicePayment $payment */
            foreach ($invoice->payments as $payment) {
                $paymentId = $payment->getKey();

                if (! $payment->is_deleted) {
                    $transactions[$paymentId]['date'] = Carbon::parse($payment->date_time)->format('d M y');

                    if ($payment->type == InvoicePaymentType::DEBIT_ORDER || $payment->type->value == 'debit-order' || $payment->reference) {
                        $transactions[$paymentId]['description'] = ucfirst($payment->reference);
                    } else {
                        $transactions[$paymentId]['description'] = ucfirst($payment->type->value).' payment';
                    }

                    $transactions[$paymentId]['payment_amount'] = $payment->amount ?? 0;
                    $transactions[$paymentId]['type'] = 'payment';
                }
            }
        }

        // Sort by date
        usort($transactions, function ($a, $b) {
            $dateA = DateTime::createFromFormat('d M y', $a['date']);
            $dateB = DateTime::createFromFormat('d M y', $b['date']);

            return $dateA <=> $dateB;
        });

        // Workout transaction rolling total
        foreach ($transactions as $index => $transaction) {
            if ($transaction['type'] == InvoiceType::INVOICE->value) {
                $rollingTotal = $rollingTotal + $transaction['invoice_amount'];
            } elseif ($transaction['type'] == InvoiceType::CREDIT_NOTE->value) {
                $rollingTotal = $rollingTotal - $transaction['invoice_amount'];
            } elseif ($transaction['type'] == 'payment') {
                $rollingTotal = $rollingTotal - $transaction['payment_amount'];
            }

            $transactions[$index]['balance'] = $rollingTotal;
        }

        return $transactions;
    }

    public function getExistingUnpaidTopupInvoiceForUserPackage(UserPackage $userPackage): ?object
    {
        return UserInvoice::query()
            ->from('finance_invoices', 'invoice')
            ->join('finance_invoice_items as invoiceItem', 'invoice.invoice_id', '=', 'invoiceItem.invoice_id')
            ->where('invoice.user_to_package_id', '=', $userPackage->getKey())
            ->whereIn('invoice.discriminator', [InvoiceDiscriminator::TOPUP_INVOICE->value, InvoiceDiscriminator::BUY_PACKAGE_INVOICE->value])
            ->where('invoice.deleted', false)
            ->whereNotIn('invoice.status', [InvoiceStatus::PAID->value, InvoiceStatus::CREDITED->value])
            ->orderByDesc('invoice.invoice_id')
            ->first();
    }

    public function getUpcomingDebitOrderInvoice(User $user, ?Location $location = null): ?UserInvoice
    {
        return UserInvoice::query()
            ->select('i.*')
            ->from('finance_invoices', 'i')
            ->join('finance_invoice_items as ii', 'ii.invoice_id', '=', 'i.invoice_id')
            ->join('user_to_batch as utb', 'utb.invoice_id', '=', 'i.invoice_id')
            ->join('debit_batches as db', 'utb.debit_batch_id', '=', 'db.debit_batch_id')
            ->join('debit_day_dates as ddd', 'db.debit_day_date_id', '=', 'ddd.debit_day_date_id')
            ->where('utb.user_id', '=', $user->getAuthIdentifier())
            ->where('ddd.debit_day_date', '>=', today()->toDateString())
            ->where('ii.discriminator', '=', 'membership')
            ->where('ii.deleted', '=', false)
            ->where('i.deleted', '=', false)
            ->where('utb.is_active', '=', true)
            ->where('db.is_processed', '=', false)
            ->when($location, function ($query) use ($location) {
                $query->where('db.box_facility_id', $location->getKey());
            })
            ->orderBy('ddd.debit_day_date')
            ->first();

    }

    public function getMembershipInvoiceItemsByFacility($boxFacility, $types, $startDate = null, $endDate = null): Collection|array
    {
        $qb = UserInvoiceItem::query()
            ->withoutGlobalScopes()
            ->from('finance_invoice_items', 'it')
            ->join('finance_invoices as i', 'i.invoice_id', '=', 'it.invoice_id')
            ->join('user_to_facility as fm', 'fm.user_to_facility_id', '=', 'i.user_to_facility_id')
            ->join('box_facility as f', 'fm.box_facility_id', '=', 'f.box_facility_id')
            ->where('it.discriminator', $types)
            ->where('f.box_facility_id', $boxFacility)
            ->where('it.deleted', false)
            ->where('i.deleted', false)
            ->distinct();

        if ($startDate && $endDate) {
            $qb->whereBetween('it.created_on', [$startDate, $endDate]);
        }

        return $qb->get();
    }

    public function getMembershipInvoiceItemsByBox($box, $types, $startDate = null, $endDate = null): Collection|array
    {
        $qb = UserInvoiceItem::query()
            ->withoutGlobalScopes()
            ->from('finance_invoice_items', 'it')
            ->join('finance_invoices as i', 'i.invoice_id', '=', 'it.invoice_id')
            ->join('user_to_facility as fm', 'fm.user_to_facility_id', '=', 'i.user_to_facility_id')
            ->join('box_facility as f', 'fm.box_facility_id', '=', 'f.box_facility_id')
            ->where('it.discriminator', $types)
            ->where('f.box_id', $box)
            ->where('it.deleted', false)
            ->where('i.deleted', false)
            ->distinct();

        if ($startDate && $endDate) {
            $qb->whereBetween('it.created_on', [$startDate, $endDate]);
        }

        return $qb->get();
    }

    public function getPaymentsForBox($box, $boxFacility = null, $type = null, $startDate = null, $endDate = null): Collection|array
    {
        $qb = UserInvoicePayment::query()
            ->withoutGlobalScopes()
            ->from('finance_payments', 'p')
            ->join('finance_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->join('user_to_facility as fm', 'i.user_to_facility_id', '=', 'fm.user_to_facility_id')
            ->join('box_facility as f', 'f.box_facility_id', '=', 'fm.box_facility_id')
            ->where('f.box_id', $box)
            ->where('p.deleted', false)
            ->select('p.*')
            ->distinct();

        if ($boxFacility) {
            $qb->where('f.box_facility_id', $boxFacility);
        }

        if ($type) {
            $qb->where('p.type', $type);
        }

        if ($startDate && $endDate) {
            $qb->whereBetween('p.date_time', [$startDate, $endDate]);
        }

        return $qb->get();
    }

    public function generateInvoiceForLeadMember(LeadMember $leadMember, $amount, DateTime $dueOn, ?UserPackage $userPackage = null): UserInvoice
    {
        $location = $leadMember->location;

        $date = clone $dueOn;
        $date->modify('first day of this month');
        $endDate = clone $date;
        $endDate->modify('last day of this month');

        $invoice = UserInvoice::create([
            'user_to_package_id' => $userPackage?->getKey(),
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($location),
            'type' => InvoiceType::INVOICE,
            'description' => $location->name.': Lead invoice',
            'status' => InvoiceStatus::UNPAID,
            'currency' => $location->tenant->memberCurrency->code,
            'amount' => $amount,
            'user_to_facility_id' => (new TenantUserService())->getLocationLeadByTenant($leadMember, $location->tenant)->getKey(),
            'created_by_id' => request()->user()?->getKey(),
            'due_on' => $dueOn,
            'period_start' => $date,
            'period_end' => $endDate,
        ]);

        UserInvoiceItem::create([
            'invoice_id' => $invoice->getKey(),
            'discriminator' => 'membership',
            'description' => $location->name.': Lead invoice',
            'quantity' => 1,
            'amount' => $amount,
            'unitPrice' => $amount,
        ]);

        return $invoice;
    }

    public function getLatestUnpaidSignUpInvoiceForUser($user)
    {
        return UserInvoice::query()
            ->join('user_to_facility', 'user_to_facility.user_id', '=', 'user_invoice.user_id')
            ->join('finance_invoice_items', 'finance_invoice_items.invoice_id', '=', 'user_to_facility.invoice_id')
            ->where('user_to_facility.user_id', $user->getKey())
            ->where('finance_invoices.type', '=', InvoiceType::INVOICE->value)
            ->where('finance_invoices.discriminator', '=', InvoiceDiscriminator::SIGN_UP_INVOICE->value)
            ->where('finance_invoices.status', '!=', InvoiceStatus::CREDITED->value)
            ->where('finance_invoices.deleted', '=', false)
            ->where('finance_invoice_items.deleted', '=', false)
            ->limit(1)
            ->orderByDesc('finance_invoices.created_on')
            ->first();
    }

    public function generateLateCancellationInvoiceForClassBooking(ClassBooking $classBooking): void
    {
        $box = $classBooking->tenant;
        $userPackage = $classBooking->userPackage;
        $locationUser = (new TenantUserService())->getLocationUserByTenant($classBooking->user, $classBooking->tenant);
        if (!$locationUser) {
            $locationUser = (new TenantUserService())->getLocationLeadByTenant($classBooking->leadMember, $classBooking->tenant);
        }

        // set the invoice dates
        $date = now()->clone();
        $date->modify('first day of this month');
        $endDate = $date->clone();
        $endDate->modify('last day of this month');

        // Create invoice
        $invoice = UserInvoice::create([
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($locationUser->location),
            'user_to_facility_id' => $locationUser->getKey(),
            'type' => InvoiceType::INVOICE->value,
            'description' => 'Late cancellation fee '.$classBooking->class->name.': '.$classBooking->classDate->class_date->format('Y-m-d').' '.$classBooking->classDate->startTime(),
            'status' => InvoiceStatus::UNPAID->value,
            'currency' => $box->memberCurrency->code,
            'user_to_package_id' => $userPackage->getKey(),
            'amount' => $classBooking->userPackage->package->late_cancellation_fee,
            'discriminator' => InvoiceDiscriminator::LATE_CANCELLATION_INVOICE->value,
            'due_on' => today(),
            'period_start' => $date,
            'period_end' => $endDate,
        ]);

        // Create invoice line item
        UserInvoiceItem::create([
            'invoice_id' => $invoice->getKey(),
            'discriminator' => 'invoice',
            'description' => 'Late cancellation fee '.$classBooking->class->name.': '.$classBooking->classDate->class_date->format('Y-m-d').' '.$classBooking->classDate->startTime(),
            'quantity' => 1,
            'UnitPrice' => $userPackage->package->late_cancellation_fee,
            'amount' => $userPackage->package->late_cancellation_fee,
        ]);

    }

    public function generateNoShowFeeForClassBooking(ClassBooking $classBooking): void
    {
        $box = $classBooking->tenant;
        $userPackage = $classBooking->userPackage;
        $locationUser = (new TenantUserService())->getLocationUserByTenant($classBooking->user, $classBooking->tenant);

        // set the invoice dates
        $date = now()->clone();
        $date->modify('first day of this month');
        $endDate = $date->clone();
        $endDate->modify('last day of this month');

        // Create invoice
        $invoice = UserInvoice::create([
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($locationUser->location),
            'user_to_facility_id' => $locationUser->getKey(),
            'type' => InvoiceType::INVOICE->value,
            'description' => 'No show fee for class: '.$classBooking->class->name.': '.$classBooking->classDate->class_date->format('Y-m-d').' '.$classBooking->classDate->startTime(),
            'status' => InvoiceStatus::UNPAID->value,
            'currency' => $box->memberCurrency->code,
            'user_to_package_id' => $userPackage->getKey(),
            'amount' => $classBooking->userPackage->package->no_show_fee,
            'discriminator' => InvoiceDiscriminator::NO_SH0W_FEE_INVOICE->value,
            'due_on' => today(),
            'period_start' => $date,
            'period_end' => $endDate,
        ]);

        // Create invoice line item
        UserInvoiceItem::create([
            'invoice_id' => $invoice->getKey(),
            'discriminator' => 'invoice',
            'description' => 'No show fee for class: '.$classBooking->class->name.': '.$classBooking->classDate->class_date->format('Y-m-d').' '.$classBooking->classDate->startTime(),
            'quantity' => 1,
            'UnitPrice' => $userPackage->package->no_show_fee,
            'amount' => $userPackage->package->no_show_fee,
        ]);

    }
}
