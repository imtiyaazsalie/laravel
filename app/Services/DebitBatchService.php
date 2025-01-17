<?php

namespace App\Services;

use App\Enums\InvoiceItemDiscriminator;
use App\Enums\InvoicePaymentType;
use App\Enums\MandateStatus;
use App\Enums\MandateType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Enums\PaymentProcessorTag;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Jobs\GoCardless\SubmitDebitBatch as GoCardlessSubmitDebitBatch;
use App\Jobs\StripeConnect\SubmitDebitBatch as StripeConnectSubmitDebitBatch;
use App\Models\DebitBatch;
use App\Models\DebitBatchStatement;
use App\Models\DebitDay;
use App\Models\DebitDayDate;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\LocationUser;
use App\Models\Mandate;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserBankingDetail;
use App\Models\UserBatch;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Models\UserOnHold;
use App\Services\PaymentGateways\GoCardlessService;
use App\Services\PaymentGateways\NetcashService;
use App\Services\PaymentGateways\ThreePeaksService;
use DateInterval;
use DateTime;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use Exception;
use Genkgo\Camt\Config;
use Genkgo\Camt\Exception\ReaderException;
use Genkgo\Camt\Reader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

class DebitBatchService
{
    public function resubmit(DebitBatch $debitBatch): array
    {
        $errors = [];
        $isResubmitSuccessful = false;
        $locationPaymentGateway = $debitBatch->location->paymentGateway;

        try {
            if ($locationPaymentGateway->isNoGateway()) {
                return [
                    'is_successful' => false,
                    'errors' => 'This facility\'s payment gateway is set to \'no gateway\'.',
                ];
            }

            $date = request()->get('date') ? Carbon::parse(request()->get('date')) : $debitBatch->debitDayDate->date;

            if ($locationPaymentGateway->isNetcash()) {
                $type = request()->get('is_process_as_same_day_batch') ? NetcashService::SUBMISSION_TYPE_SAME_DAY : NetcashService::SUBMISSION_TYPE_TWO_DAY;
                $results = (new NetcashService())->submitNetcashBatch($debitBatch, $date, $type);

                if (is_bool($results) && $results) {
                    $isResubmitSuccessful = true;
                } elseif (is_string($results)) {
                    $errors[] = $results;
                } elseif (is_array($results)) {
                    $errors = $results;
                }
            } elseif ($locationPaymentGateway->isThreePeaks()) {
                (new ThreePeaksService())->submitDebitBatch($debitBatch, $date);

                $isResubmitSuccessful = true;
            } elseif ($locationPaymentGateway->isGoCardless()) {
                GoCardlessSubmitDebitBatch::dispatch($debitBatch, $date)->onQueue('finance');

                $isResubmitSuccessful = true;
            } elseif ($locationPaymentGateway->isStripeConnect()) {
                StripeConnectSubmitDebitBatch::dispatch($debitBatch, $date)->onQueue('finance');

                $isResubmitSuccessful = true;
            }
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface|Exception $ex) {
            $errors[] = $ex->getMessage();
            Log::error($ex);
        }

        return [
            'isSuccessful' => $isResubmitSuccessful,
            'errors' => ! empty($errors) ? implode(', ', $errors) : null,
        ];
    }

    public function createDebitDayDates(?Carbon $firstDayOfMonth): \Illuminate\Support\Collection
    {
        // Get or create debit day dates
        $debitDays = DebitDay::query()->where('is_active', true)->get();

        $dates = $debitDays->map(fn (DebitDay $debitDay) => [
            'debit_day_id' => $debitDay->getKey(),
            'debit_day_date' => $debitDay->getDate($firstDayOfMonth)->toDateString(),
        ]);

        $existingDates = DebitDayDate::query()
            ->whereIn('debit_day_date', $dates->pluck('debit_day_date')->toArray())
            ->get('debit_day_date')
            ->map(fn ($date) => $date->debit_day_date->toDateString())
            ->toArray();

        $dates = $dates->filter(fn ($date) => ! in_array($date['debit_day_date'], $existingDates));

        if ($dates->isNotEmpty()) {
            $dates->each(fn ($date) => DebitDayDate::create([
                'debit_day_id' => $date['debit_day_id'],
                'debit_day_date' => $date['debit_day_date'],
                'is_active' => true,
            ]));
        }

        return DebitDayDate::query()
            ->whereBetween('debit_day_date', [$firstDayOfMonth->clone()->firstOfMonth()->toDateString(), $firstDayOfMonth->clone()->lastOfMonth()->toDateString()])
            ->where('is_active', '=', true)
            ->get();
    }

    public function deactivateAndRegenerateFutureUserBatchesForUser(TenantUser $userBoxMembership, ?UserBankingDetail $userBankingDetails = null): void
    {
        $box = $userBoxMembership->tenant;
        $user = $userBoxMembership->user;
        $usersCurrentBoxFacility = (new TenantUserService())->getLocationUserByTenant($user, $box)?->location;

        if (! $userBankingDetails instanceof UserBankingDetail) {
            $userBankingDetails = $userBoxMembership->bankAccount;

            if ($userBoxMembership->user_debit_status_id !== UserDebitStatus::DEBIT_ORDER || ! $userBankingDetails instanceof UserBankingDetail) {
                return;
            }
        }

        // Check if user is on hold
        $isOnHold = UserOnHold::query()
            ->where('user_id', '=', $user->getKey())
            ->where('box_id', '=', $box->getKey())
            ->first();

        // All existing invoice items except the membership and discount invoice items
        $existingInvoiceItems = [];

        // Remove user from all future debit batches that have not yet been processed and also remove the invoices
        $futureUserBatches = (new DebitBatchService())->getAllUnprocessedActiveBatchesForUser($user, $box);

        /** @var UserBatch $userBatch */
        foreach ($futureUserBatches as $userBatch) {

            $userBatch->update([
                'is_active' => false,
            ]);

            if (! $userBatch->invoice || $userBatch->invoice->deleted) {
                continue;
            }

            /** @var UserInvoiceItem $invoiceItem */
            foreach ($userBatch->invoice->invoiceItems as $invoiceItem) {
                // Add to the invoice list if item is not deleted and not a membership and not a discount and not a special discount
                if (! $invoiceItem->deleted && ! in_array($invoiceItem->discriminator, ['membership', 'discount', 'special_discount'])) {
                    $existingInvoiceItems[] = $invoiceItem;
                }
            }

            // Mark the invoice as deleted
            $this->markAsDeleted($userBatch->invoice);

            // Update batch total
            $this->updateBatchTotal($userBatch->debitBatch);
        }

        // User is not on-hold and is active
        if (! $isOnHold && $userBoxMembership->user_status_id == UserStatus::ACTIVE) {
            /***************************************************************
             * NOW ADD TO BATCH
             ****************************************************************/
            $futureDebitBatches = $this->getFutureDebitDates($usersCurrentBoxFacility->box_facility_id, $userBankingDetails->debitDay->getKey());

            foreach ($futureDebitBatches as $debitBatch) {
                if ((new UserPackageService())->getActiveUserPackagesExcludingLimitedPackagesForTenant($user, $box, $debitBatch->debitDayDate->debit_day_date)->isEmpty()) {
                    continue;
                }

                $debitBatchEntity = DebitBatch::query()->findOrFail($debitBatch->debit_batch_id);

                // Generate an invoice for a user
                $invoice = (new FinanceService())->generateInvoiceForUser($user, $debitBatchEntity);

                if ($invoice instanceof UserInvoice) {
                    // Add existing invoice items if they were on a previous invoice that was deleted
                    if (! empty($existingInvoiceItems)) {
                        /** @var UserInvoiceItem $existingInvoiceItem */
                        foreach ($existingInvoiceItems as $existingInvoiceItem) {
                            // Check if the package for the pro-rate was deleted
                            if ($existingInvoiceItem->discriminator === InvoiceItemDiscriminator::PRORATE->value) {
                                $description = str_replace(' (Prorate)', '', $existingInvoiceItem->description);

                                // Search for line item with same line item
                                $invoiceLineItems = $invoice->invoiceItems->filter(function (UserInvoiceItem $invoiceItem) use ($description) {
                                    return $invoiceItem->description === $description;
                                });

                                if ($invoiceLineItems->isEmpty()) {
                                    continue;
                                }
                            }

                            $existingInvoiceItem->update([
                                'invoice_id' => $invoice->getKey(),
                                'deleted' => false,
                            ]);

                            $invoice->update([
                                'amount' => $invoice->amount + $existingInvoiceItem->amount,
                            ]);
                        }

                        // This is so that it only gets added to the first invoice
                        $existingInvoiceItems = null;
                    }

                    $this->createUserBatch($user, $invoice, $debitBatchEntity);
                    $this->updateBatchTotal($debitBatchEntity);
                }
            }
            /***************************************************************
             * FINISHED WITH ADD TO BATCH
             ****************************************************************/
        }
    }

    public function getAllUnprocessedActiveBatchesForUser(User $user, Tenant $box): Collection|array
    {
        return UserBatch::query()
            ->join('debit_batches', 'debit_batches.debit_batch_id', '=', 'user_to_batch.debit_batch_id')
            ->join('debit_day_dates', 'debit_day_dates.debit_day_date_id', '=', 'debit_batches.debit_day_date_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'debit_batches.box_facility_id')
            ->where('user_to_batch.user_id', $user->getAuthIdentifier())
            ->where('box_facility.box_id', $box->getKey())
            ->where('user_to_batch.is_active', '=', true)
            ->where('debit_batches.is_processed', '=', false)
            ->where('debit_day_dates.debit_day_date', '>', today()->toDateString())
            ->orderBy('debit_day_dates.debit_day_date', 'ASC')
            ->get();
    }

    public function markAsDeleted(UserInvoice $invoice): UserInvoice
    {
        $invoice->update(['deleted' => true]);

        foreach ($invoice->invoiceItems as $invoiceItem) {
            $invoiceItem->update(['deleted' => true]);
        }

        // Delete all the payments linked to this invoice
        foreach ($invoice->payments()->where('deleted', '=', false)->get() as $invoicePayment) {
            $invoicePayment->update(['deleted' => true]);
        }

        return $invoice;
    }

    public function getDebitBatchStatementForDebitBatchAndDate(DebitBatch $debitBatch, Carbon $date): ?object
    {
        return DebitBatchStatement::query()
            ->where('debit_batch_id', '=', $debitBatch)
            ->whereDate('dt', '=', $date->toDateString())
            ->orderBy('debit_batch_statement_download_request_id', 'DESC')
            ->first();
    }

    public function updateBatchTotal(DebitBatch $debitBatch): void
    {
        $debitBatch->updateBatchTotal();
    }

    public function getTotalForDebitBatch(DebitBatch $debitBatch): int
    {
        return UserBatch::query()
            ->where('debit_batch_id', $debitBatch->getKey())
            ->where('is_active', '=', true)
            ->sum('amount_editable');
    }

    public function getFutureDebitDates(int $facilityId, int|string $debitDay): Collection|array
    {
        return DebitBatch::query()
            ->from('debit_batches', 'db')
            ->join('debit_day_dates as ddd', 'db.debit_day_date_id', '=', 'ddd.debit_day_date_id')
            ->join('debit_days as dd', 'ddd.debit_day_id', '=', 'dd.debit_day_id')
            ->where(function ($query) use ($debitDay) {
                if ($debitDay == 7) {
                    $query->where('ddd.debit_day_date', '>', today());
                } else {
                    $query->where('ddd.debit_day_date', '>', today()->addDays(5));
                }
            })
            ->where('db.box_facility_id', '=', $facilityId)
            ->where('dd.debit_day_id', '=', $debitDay)
            ->where('db.is_processed', '=', 0)
            ->where('ddd.is_active', '=', 1)
            ->get();
    }

    public function createUserBatch(User $user, UserInvoice $invoice, DebitBatch $debitBatch, ?bool $isManuallyAdded = false): Builder|Model
    {
        $userBatch = UserBatch::query()->create([
            'debit_batch_id' => $debitBatch->getKey(),
            'amount_editable' => $invoice->amount,
            'user_id' => $user->getKey(),
            'invoice_id' => $invoice->getKey(),
            'is_active' => true,
            'manually_added' => $isManuallyAdded,
        ]);
        (new DebitBatchService())->updateBatchTotal($userBatch->debitBatch);

        return $userBatch;
    }

    public function generateNamibianDebitBatchExportContent(DebitBatch $debitBatch): array|bool|string
    {
        $location = $debitBatch->location;

        if ($location->tenant->region->name !== 'Namibia') {
            return false;
        }

        $userBatches = UserBatch::query()
            ->join('users', 'user_to_batch.user_id', '=', 'users.user_id')
            ->where('user_to_batch.debit_batch_id', '=', $debitBatch->getKey())
            ->where('user_to_batch.is_active', '=', true)
            ->orderBy('users.name', 'ASC')
            ->get();

        $headerRowLines = [
            'HDR', // record identifier
            Arr::get($location->extra_parameters, 'bank_user_code'),
            str_pad(Arr::get($location->extra_parameters, 'bank_user_name'), 20, ' ', STR_PAD_RIGHT), // user name (20)
            '000000', // filler (6)
            '03', // file version number (2)
            '000000', // header sequence number (6)
            $debitBatch->debitDayDate->date->format('Ymd'), // date (8),
        ];

        $headerRow = implode('', $headerRowLines);
        $content = str_pad($headerRow, 150, ' ', STR_PAD_RIGHT);
        $transactionNumber = 0;
        $total = 0;

        /** @var UserBatch $userBatch */
        foreach ($userBatches as $userBatch) {
            $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($userBatch->user_id, $location->tenant_id);

            if (! $tenantUser instanceof TenantUser) {
                continue;
            }

            $bankingDetails = $tenantUser->bankAccount;

            if (! $bankingDetails) {
                continue;
            }

            $transactionNumber++;
            $total += $userBatch->invoice->amount_in_cents;

            $transactionRow = [
                'PAY', // record identifier (3)
                str_pad(sprintf('%s%s', trim($userBatch->user?->surname), substr($userBatch->user?->name, 0, 1)), 30, ' ', STR_PAD_RIGHT), // account holder name (30)
                '03', // file version (2)
                str_pad($transactionNumber, 6, '0', STR_PAD_LEFT), // transaction number (6)
                Arr::get($location->extra_parameters, 'bank_nominated_account'), // nominated account (10)
                substr($bankingDetails->branch_code, 0, 6), // homing branch (6)
                str_pad($bankingDetails->account_number, 19, '0', STR_PAD_LEFT), // homing account number (19)
                $bankingDetails->account_type->namibiaExportCode(), // homing account type (1)
                str_pad($userBatch->invoice->amount_in_cents, 11, '0', STR_PAD_LEFT), // transaction amount (11)
                $debitBatch->debitDayDate->date->format('Ymd'), // action date (8)
                str_pad(Arr::get($location->extra_parameters, 'bank_user_code').Arr::get($location->extra_parameters, 'bank_user_name'), 20, ' ', STR_PAD_RIGHT), // user reference (20)
                '2', // type of service - 2 = two day (1)
                '50', // type of transaction - 50 = debit (2)
                '0', // tax code (1)
                str_pad($userBatch->user_id, 30, '0', STR_PAD_LEFT), // reference number (30)
            ];

            $content .= PHP_EOL.implode('', $transactionRow);
        }

        $footerRowLines = [
            'ZZZ', // record identifier (3)
            str_pad($transactionNumber, 6, '0', STR_PAD_LEFT), // number of transactions (6)
            str_pad($total, 11, '0', STR_PAD_LEFT), // total amount in cents (11)
            '             ', // filler (13)
            '03', // file version number (2)
            '999999', // sequence number (6)
            str_pad('', 109, ' '), // filler (109)
        ];

        $content .= PHP_EOL.implode('', $footerRowLines);

        return str_replace(["\r\n", "\r", "\n"], "\r\n", $content);
    }

    public function generateSepaDebitBatchExportContent(DebitBatch $debitBatch, string $painFormat, ?bool $isProcessAsBatch = false): string
    {
        $location = $debitBatch->location;
        $tenant = $location->tenant;

        $locationPaymentGateway = (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::DEBIT_ORDER, PaymentGateway::SEPA)->first();

        if (! $locationPaymentGateway instanceof LocationPaymentGateway || ! $locationPaymentGateway->settings instanceof LocationPaymentGatewaySettings) {
            abort(Response::HTTP_NOT_FOUND, 'This location does not have the necessary SEPA debit-order details to do the export.');
        }

        $sepaSettings = $locationPaymentGateway->settings;

        if (! $sepaSettings->creditor_iban || ! $sepaSettings->creditor_id || ! $sepaSettings->creditor_bic) {
            abort(Response::HTTP_BAD_REQUEST, 'Please make sure that your SEPA payment gateway details are correct.');
        }

        $groupHeaderIdentification = $debitBatch->debitDayDate->date->format('Ymd').'-'.$location->getKey().'-'.$debitBatch->getKey();
        $directDebit = TransferFileFacadeFactory::createDirectDebit($groupHeaderIdentification, $tenant->name, $painFormat);

        $paymentName = 'direct-debit-'.$debitBatch->getKey();

        try {
            $directDebit->addPaymentInfo($paymentName, [
                'id' => $paymentName,
                'dueDate' => $debitBatch->debitDayDate->date,
                'creditorName' => $tenant->name,
                'creditorAccountIBAN' => $sepaSettings->creditor_iban,
                'creditorAgentBIC' => $sepaSettings->creditor_bic,
                'seqType' => PaymentInformation::S_RECURRING,
                'creditorId' => $sepaSettings->creditor_id,
                'localInstrumentCode' => 'CORE',
                'batchBooking' => $isProcessAsBatch,
            ]);

            foreach ((new UserBatchService)->getUserBatchesForDebitBatchSubmission($debitBatch) as $userBatch) {
                $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($userBatch->user_id, $location->tenant_id);

                if (! $tenantUser instanceof TenantUser) {
                    continue;
                }

                $user = $userBatch->user;
                $bankingDetails = $tenantUser->bankAccount;
                $mandate = (new MandateService())->getLatestMandate($user, $debitBatch->location->tenant, MandateType::SEPA);

                if (! $mandate instanceof Mandate || $mandate->status !== MandateStatus::ACTIVE) {
                    continue;
                }

                // Add a Single Transaction to the named payment
                $directDebit->addTransfer($paymentName, [
                    'amount' => $userBatch->amount_in_cents,
                    'debtorIban' => trim(Str::replace(' ', '', $bankingDetails->iban)),
                    'debtorBic' => trim(Str::replace(' ', '', $bankingDetails->bic)),
                    'debtorName' => trim($user->full_name),
                    'debtorMandate' => $mandate->reference,
                    'debtorMandateSignDate' => $mandate->signed_at->format('d.m.Y'),
                    'remittanceInformation' => 'Invoice '.$userBatch->invoice->code,
                    'endToEndId' => $userBatch->getKey(),
                    'amendedDebtorAccount' => false,
                ]);
            }
        } catch (InvalidArgumentException|\Digitick\Sepa\Exception\InvalidArgumentException $e) {
            Log::error($e->getMessage());

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Something went wrong during the creation of the debit batch export.');
        }

        return $directDebit->asXML();
    }

    public function reconcileSepaDebitBatch(UploadedFile $bankStatementFile, int $tenantId): array
    {
        $results = ['successes' => [], 'failures' => []];

        $reader = new Reader(Config::getDefault());

        try {
            $message = $reader->readFile($bankStatementFile);
        } catch (ReaderException $e) {
            abort($e->getCode() ?? 400, $e->getMessage());
        }

        $statements = $message->getRecords();

        $debitBatchLogs = [];

        foreach ($statements as $statement) {
            $entries = $statement->getEntries();

            foreach ($entries as $entry) {
                $transactionDetail = $entry->getTransactionDetail();

                $endToEndId = $transactionDetail->getReference()->getEndToEndId();

                if (! $endToEndId) {
                    continue;
                }

                $userToBatch = UserBatch::query()
                    ->join('debit_batches', 'user_to_batch.debit_batch_id', 'debit_batches.debit_batch_id')
                    ->join('box_facility', 'debit_batches.box_facility_id', 'box_facility.box_facility_id')
                    ->where('user_to_batch.user_to_batch_id', '=', intval($endToEndId))
                    ->where('box_facility.box_id', '=', $tenantId)
                    ->first();

                if (! $userToBatch || ! $userToBatch->invoice) {
                    $results['failures'][] = [
                        'endToEndId' => $endToEndId,
                        'message' => 'No user batch or invoice for transaction entry',
                    ];

                    continue;
                }

                $invoice = $userToBatch->invoice;
                $debitBatch = $userToBatch->debitBatch;

                if (! array_key_exists($debitBatch->getKey(), $debitBatchLogs)) {
                    $debitBatchLogs[$debitBatch->getKey()] = ['debitBatch' => $debitBatch, 'logs' => []];
                }

                $reference = $transactionDetail->getReference()->getTransactionId() ?? 'Debit order payment - '.$entry->getBookingDate()->format('Y-m-d');

                if ($invoice->getExistingPaymentsBy($userToBatch->amount, $reference, InvoicePaymentType::DEBIT_ORDER->value)->count() > 0) {
                    $results['failures'][] = [
                        'endToEndId' => $endToEndId,
                        'message' => 'This payment has already been created for this invoice',
                        'invoice' => [
                            'id' => $invoice->getKey(),
                            'code' => $invoice->code,
                        ],
                    ];

                    $debitBatchLogs[$debitBatch->getKey()]['logs'][] = "This payment has already been created for this invoice($invoice->code)";

                    continue;
                }

                $paymentAmount = $entry->getAmount()->getAmount() / 100;
                $tagIds = [Tag::query()->paymentTag(PaymentProcessorTag::SEPA)->first()->getKey()];
                (new InvoiceService())->createPaymentForInvoice($invoice, InvoicePaymentType::DEBIT_ORDER, $paymentAmount, null, $reference, $tagIds);

                $debitBatchLogs[$debitBatch->getKey()]['logs'][] = "Added payment for invoice: {$userToBatch->getInvoice()->getKey()}. Payment amount: $paymentAmount";

                $results['successes'][] = [
                    'endToEndId' => $endToEndId,
                    'message' => 'Invoice successfully reconciled',
                    'invoice' => [
                        'id' => $invoice->getKey(),
                        'code' => $invoice->code,
                    ],
                ];
            }
        }

        foreach ($debitBatchLogs as $debitBatchLog) {
            $debitBatch = $debitBatchLog['debitBatch'];
            $logs = $debitBatchLog['logs'];

            $debitBatch->startLogEntry();

            foreach ($logs as $log) {
                $debitBatch->log($log);
            }

            $debitBatch->endLogEntry();
            $debitBatch->save();
        }

        return $results;
    }

    public function shouldTenantUserBeAddedToDebitBatch(TenantUser $tenantUser, DebitBatch $debitBatch, ?bool $isReturnErrorMessage = false): bool|string|null
    {
        // is user on hold?
        if (UserOnHold::query()->where('box_id', $tenantUser->tenant_id)->where('user_id', '=', $tenantUser->user_id)->count() > 0) {
            return $isReturnErrorMessage ? 'User is on-hold' : false;
        }

        //does the user have an active user location record
        $hasUserLocation = $tenantUser
            ->userLocation()
            ->where('user_to_facility.end_date', '>', today()->toDateString())
            ->where('user_to_facility.box_facility_id', $debitBatch->location_id)
            ->first();

        if (! $hasUserLocation) {
            return $isReturnErrorMessage ? 'User does not have active user to facility for this location.' : false;
        }

        // is user already in batch?
        $hasUserBatches = UserBatch::query()
            ->where('user_id', '=', $tenantUser->user_id)
            ->where('debit_batch_id', '=', $debitBatch->debit_batch_id)
            ->where('is_active', '=', true)
            ->count() > 0;

        if ($hasUserBatches) {
            return $isReturnErrorMessage ? 'User is already on the batch' : false;
        }

        // does user not have active banking details?
        if ($tenantUser->bankAccount()->doesntExist()) {
            return $isReturnErrorMessage ? 'User does not have active banking details' : false;
        }

        // check that this user has an active package(s)
        if ((new UserPackageService())->getActiveUserPackagesExcludingLimitedPackagesForTenant($tenantUser->user, $tenantUser->tenant, $debitBatch->debitDayDate->date)->count() <= 0) {
            return $isReturnErrorMessage ? 'User does not have an active package for the selected debit batch date: '.$debitBatch->debitDayDate->date->format('Y-m-d') : false;
        }

        return $isReturnErrorMessage ? null : true;
    }

    public function deactivateFutureUserBatchesForUserBoxMembership(TenantUser $userBoxMembership): void
    {
        $affectedDebitBatches = [];
        $futureUserBatches = (new DebitBatchService())->getAllUnprocessedActiveBatchesForUser($userBoxMembership->user, $userBoxMembership->tenant);

        /** @var UserBatch $userBatch */
        foreach ($futureUserBatches as $userBatch) {
            $userBatch->setAttribute('is_active', false);

            if (! $userBatch->invoice || $userBatch->invoice->deleted) {
                continue;
            }

            $userBatch->save();
            $this->markAsDeleted($userBatch->invoice);

            $affectedDebitBatches[] = $userBatch->debitBatch;
        }

        // Update totals for debit batches
        foreach ($affectedDebitBatches as $debitBatch) {
            // Update batch total
            $this->updateBatchTotal($debitBatch);
        }
    }

    public function generateFutureDebitBatchesForUser(Location $location, DebitDay $debitDay, User $user, $proRate = null): void
    {
        $futureDebitBatches = $this->getFutureDebitDates($location->getKey(), $debitDay->getKey());

        foreach ($futureDebitBatches as $debitBatch) {
            $debitBatchEntity = DebitBatch::query()->find($debitBatch->debit_batch_id);

            $invoice = (new FinanceService())->generateInvoiceForUser($user, $debitBatchEntity, $proRate);

            if ($invoice instanceof UserInvoice) {
                $this->createUserBatch($user, $invoice, $debitBatchEntity);
            }

            $this->updateBatchTotal($debitBatchEntity);
        }
    }

    public function ensureOnHoldReleasedUserIsOnNextUnprocessedBatchWithAmount(UserOnHold $onHoldUser): void
    {
        $tenantUser = $onHoldUser->userTenant;
        $tenant = $tenantUser->tenant;
        $user = $tenantUser->user;
        $amount = $onHoldUser->pro_rata_fee;

        // Check if user has an active package
        if ((new UserPackageService())->getActiveUserPackagesForTenant($user, $tenant)->count() <= 0) {
            return;
        }

        // Get the next upcoming unprocessed user debit batch for box
        $nextUpcomingUserBatch = UserBatch::query()
            ->join('debit_batches', 'user_to_batch.debit_batch_id', '=', 'debit_batches.debit_batch_id')
            ->join('debit_day_dates', 'debit_day_dates.debit_day_date_id', '=', 'debit_batches.debit_day_date_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'debit_batches.box_facility_id')
            ->where('user_to_batch.user_id', $onHoldUser->user_id)
            ->where('user_to_batch.is_active', false)
            ->where('box_facility.box_id', $onHoldUser->box_id)
            ->where('debit_batches.is_processed', false)
            ->where('debit_day_dates.debit_day_date', '>', now()->toDateString())
            ->orderBy('debit_day_dates.debit_day_date')
            ->first();

        if ($nextUpcomingUserBatch) {
            $this->reactivateUserBatchWithAmount($nextUpcomingUserBatch, $amount);

            return;
        }

        if ((! $tenantUser->isDebitOrder()) || $tenantUser->bankAccount()->doesntExist() || ! $tenantUser->bankAccount->debitDay) {
            return;
        }

        // If no future UserBatch and user is a debit batch user, create a new UserBatch with pro-rate amount
        $dateInterval = new DateTime();
        $dateInterval->add(new DateInterval($tenantUser->bankAccount->debitDay->interval));

        $debitDayDate = DebitDayDate::query()
            // ->join('debit_days', 'debit_days.debit_day_id', 'debit_day_dates.debit_day_id')
            ->where('debit_day_dates.is_active', true)
            ->where('debit_day_dates.debit_day_id', $tenantUser->bankAccount->debit_day_id)
            ->where('debit_day_dates.debit_day_date', '>', $dateInterval)
            ->orderBy('debit_day_dates.debit_day_date')
            ->first();

        if (! $debitDayDate) {
            return;
        }

        // Get debit batch for debit day or create one
        $userLocation = LocationUser::query()
            ->where('user_id', $tenantUser->user_id)
            ->whereRelation('location', 'box_id', '=', $tenantUser->tenant_id)
            ->first();

        $debitBatch = $this->getOrCreateDebitBatchForDebitDayDateAndLocation($debitDayDate, $userLocation->location);

        if (! $amount || $amount == 0) {
            $amount = (new FinanceService())->calculateMemberFee($user, $tenant, $debitBatch->debitDayDate->debit_day_date, true);
        }

        // Generate invoice for user
        $invoice = (new FinanceService())->generateOnHoldUserProRateInvoice($user, $tenant, $amount, $debitDayDate->debit_day_date);

        $userDebitBatchOrInvoice = $this->ensureUserIsOnBatch($user, $debitBatch, $invoice);

        // If an existing user debit batch was found make sure that the amount is correct
        if ($userDebitBatchOrInvoice instanceof DebitBatch && $userDebitBatchOrInvoice->amount_editable !== $amount) {
            $userDebitBatchOrInvoice->update([
                'amount_editable' => $amount,
            ]);
        }
    }

    private function reactivateUserBatchWithAmount(UserBatch $userBatch, float $amount): void
    {
        $userBatch->refresh();
        $userBatch->updateQuietly([
            'amount' => $amount,
            'is_active' => 1,
        ]);

        $invoice = $userBatch->invoice;

        $invoice->update([
            'amount' => $amount,
            'deleted' => false,
        ]);

        $originalAmount = $userBatch->invoice->amount;

        $invoiceItem = $userBatch->invoice->invoiceItems()->withTrashed()->get()->filter(function (UserInvoiceItem $item) use ($originalAmount) {
            return $item->discriminator === InvoiceItemDiscriminator::MEMBERSHIP->value && floatval($item->amount) === $originalAmount;
        })->first();

        if ($invoiceItem) {
            $invoiceItem->update([
                'amount' => $amount,
                'unitPrice' => $amount,
                'deleted' => false,
            ]);
        }

        $this->updateBatchTotal($userBatch->debitBatch);
    }

    public function getOrCreateDebitBatchForDebitDayDateAndLocation(DebitDayDate|int $debitDayDate, Location|int $location): DebitBatch
    {
        $debitDayDateId = $debitDayDate instanceof DebitDayDate ? $debitDayDate->getKey() : $debitDayDate;
        $locationId = $location instanceof Location ? $location->getKey() : $location;

        $debitBatch = DebitBatch::query()
            ->where('debit_batches.debit_day_date_id', $debitDayDateId)
            ->where('debit_batches.box_facility_id', $locationId)
            ->where('debit_batches.is_processed', '=', false)
            ->first();

        if ($debitBatch instanceof DebitBatch) {
            return $debitBatch;
        }

        return DebitBatch::create([
            'debit_day_date_id' => $debitDayDateId,
            'box_facility_id' => $locationId,
            'is_processed' => false,
        ]);
    }

    public function ensureUserIsOnBatch(User $user, DebitBatch $debitBatch, ?UserInvoice $invoice = null)
    {
        $existingUserBatch = UserBatch::query()
            ->where('user_to_batch.user_id', $user->getAuthIdentifier())
            ->where('user_to_batch.debit_batch_id', $debitBatch->getKey())
            ->where('user_to_batch.is_active', true)
            ->first();

        if ($existingUserBatch) {
            return $existingUserBatch;
        }

        $invoice = $invoice ?: (new FinanceService())->generateInvoiceForUser($user, $debitBatch, false);

        return $invoice ? $this->createUserBatch($user, $invoice, $debitBatch) : null;
    }

    public function addInvoiceToDebitBatch(UserInvoice $invoice, $debitBatchId = null): void
    {
        if ($invoice->userBatch instanceof UserBatch) {
            abort(400, 'This invoice is already linked to a debit batch.');
        }

        $locationUser = $invoice->userLocation;

        if (! $locationUser instanceof LocationUser) {
            abort(400, 'Only invoices that are linked to a member can be added to a debit batch.');
        }

        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($locationUser->user, $locationUser->location->tenant);

        if ($tenantUser->status !== UserStatus::ACTIVE) {
            abort(400, 'This member is not active.');
        }

        if ($tenantUser->debit_status !== UserDebitStatus::DEBIT_ORDER) {
            abort(400, "Member's payment details are not set to debit order.");
        }

        $userBankingDetails = $tenantUser->bankAccount;

        if (! $userBankingDetails instanceof UserBankingDetail) {
            abort(404, 'Member does not have active banking details.');
        }

        $location = $locationUser->location;

        if (in_array($location->paymentGateway->value, [PaymentGateway::GO_CARDLESS->value, PaymentGateway::SEPA->value])) {
            $mandate = null;

            if ($location->getPaymentGateway()->getId() === PaymentGateway::GO_CARDLESS->value) {
                $mandate = (new GoCardlessService())->getMandateForUser($locationUser->user, $locationUser->location, MandateStatus::activeStatusValues());
            } elseif ($location->getPaymentGateway()->getId() === PaymentGateway::SEPA->value) {
                $mandate = (new MandateService())->getLatestMandateByStatus($locationUser->user, $location->tenant, MandateStatus::ACTIVE, MandateType::SEPA);
            }

            if (! $mandate) {
                abort(404, 'Member does not have an active mandate.');
            }
        }

        if ($debitBatchId) {
            $debitBatch = DebitBatch::findOrFail($debitBatchId);

            if (! $debitBatch instanceof DebitBatch) {
                abort(404, 'Debit batch could not be found.');
            }

            if ($debitBatch->isProcessed()) {
                abort(400, 'This debit batch has already been processed.');
            }
        } else {
            $debitDay = $userBankingDetails->debitDay->getKey();

            $debitBatch = DebitBatch::query()
                ->join('debit_day_dates', 'debit_batches.debit_day_date_id', '=', 'debit_day_dates.debit_day_date_id')
                ->where('debit_batches.box_facility_id', '=', $location->getKey())
                ->where('debit_batches.is_processed', '=', 0)
                ->where('debit_day_dates.debit_day_id', '=', $debitDay)
                ->where('debit_day_dates.debit_day_date', '>', today())
                ->where('debit_day_dates.is_active', '=', 1)
                ->first();
        }

        if (! $debitBatch instanceof DebitBatch) {
            abort(404, 'This invoice cannot be added to a batch, as there are no upcoming unprocessed batches.');
        }

        $this->createUserBatch($locationUser->user, $invoice, $debitBatch);
    }
}
