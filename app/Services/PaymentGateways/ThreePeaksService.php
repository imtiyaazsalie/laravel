<?php

namespace App\Services\PaymentGateways;

use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentProcessorTag;
use App\Enums\ThreePeaksApiProcessingStatus;
use App\Enums\ThreePeaksBankProcessingStatus;
use App\Enums\ThreePeaksCheckDigitVerificationStatus;
use App\Enums\ThreePeaksDebitStatus;
use App\Enums\ThreePeaksErrors;
use App\Enums\ThreePeaksPaidOrUnpaidStatus;
use App\Enums\ThreePeaksSubmissionStatus;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Exceptions\ThreePeaksAuthenticationException;
use App\Exceptions\ThreePeaksException;
use App\Http\Resources\UserResource;
use App\Models\DebitBatch;
use App\Models\LocationPaymentGateway;
use App\Models\Tag;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserBankingDetail;
use App\Models\UserBatch;
use App\Models\UserInvoicePayment;
use App\Services\CrmService;
use App\Services\TagsService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ThreePeaksService
{
    private function getHeaders(): array
    {
        return [
            'cache-control: no-cache',
            'content-type: application/x-www-form-urlencoded',
            'my-token: '.now()->timestamp,
        ];
    }

    public function listSubmissions(int $locationId): array
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($locationId);

        $auth = $this->authenticate($locationPaymentGateway);

        $response = Http::asForm()
            ->withHeaders($this->getHeaders())
            ->post(config('threepeaks.api.url'), [
                'f' => 'listsubs',
                'session' => $auth['session'],
                'cref' => $auth['reference'],
            ]);

        $response->throw();

        $xml = simplexml_load_string(trim($response->body()));

        if ($xml === false) {
            throw new ThreePeaksException('XML format error listing submissions.');
        }

        $this->logout($locationPaymentGateway, $auth['session']);

        $submissions = new Collection();

        foreach ($xml->sub as $submission) {
            $submissions->add($this->convertSubmissionInfoToArray($submission));
        }

        return $submissions->toArray();
    }

    public function getSubmissionInformation(int $locationId, int $submissionId): array
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($locationId);
        $auth = $this->authenticate($locationPaymentGateway);

        $response = Http::asForm()
            ->withHeaders($this->getHeaders())
            ->post(config('threepeaks.api.url'), [
                'f' => 'subi',
                'subid' => $submissionId,
                'session' => $auth['session'],
                'cref' => $auth['reference'],
            ]);

        $response->throw();

        $xml = simplexml_load_string(trim($response->body()));

        if ($xml === false) {
            throw new ThreePeaksException("XML format error getting submission information for debit batch submission ID: $submissionId");
        }

        $this->logout($locationPaymentGateway, $auth['session']);

        return $this->convertSubmissionInfoToArray($xml->sub);
    }

    public function getDebitsInSubmission(int $locationId, int $submissionId): array
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($locationId);
        $auth = $this->authenticate($locationPaymentGateway);

        $response = Http::asForm()
            ->withHeaders($this->getHeaders())
            ->post(config('threepeaks.api.url'), [
                'f' => 'subid',
                'subid' => $submissionId,
                'session' => $auth['session'],
                'cref' => $auth['reference'],
            ]);

        $response->throw();

        $xml = simplexml_load_string(trim($response->body()));

        if ($xml === false) {
            throw new ThreePeaksException("XML format error getting debits for debit batch submission ID: $submissionId");
        }

        $this->logout($locationPaymentGateway, $auth['session']);

        $debits = [];

        foreach ($xml->debit as $debitInfo) {
            $debits[] = $this->convertSubmissionDebitsToArray($debitInfo);
        }

        return $debits;
    }

    public function getValidationInformation(int $locationId, int $submissionId): array
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($locationId);
        $auth = $this->authenticate($locationPaymentGateway);

        $response = Http::asForm()
            ->withHeaders($this->getHeaders())
            ->post(config('threepeaks.api.url'), [
                'f' => 'subval',
                'subid' => $submissionId,
                'session' => $auth['session'],
                'cref' => $auth['reference'],
            ]);

        $response->throw();

        $xml = simplexml_load_string(trim($response->body()));

        if ($xml === false) {
            throw new ThreePeaksException("XML format error getting validation information for debit batch submission ID: $submissionId");
        }

        $this->logout($locationPaymentGateway, $auth['session']);

        $debits = [];

        if (isset($xml->debit)) {
            foreach ($xml->debit as $debit) {
                $debits[] = [
                    'user' => new UserResource(User::find((int) $debit->ref)),
                    'actionDate' => ThreePeaksErrors::from((int) $debit->adate)->toString(),
                    'accholder' => ThreePeaksErrors::from((int) $debit->accholder)->toString(),
                    'description' => ThreePeaksErrors::from((int) $debit->description)->toString(),
                    'bankacc' => ThreePeaksErrors::from((int) $debit->bankacc)->toString(),
                    'bankcode' => ThreePeaksErrors::from((int) $debit->bankcode)->toString(),
                ];
            }

            return $debits;
        } else {
            return [
                'code' => (string) $xml->subval->code,
                'note' => (string) $xml->subval->code->attributes()->{'note'},
            ];
        }
    }

    public function submitDebitBatch(DebitBatch $debitBatch, ?Carbon $processDateOverride = null): void
    {
        $location = $debitBatch->location;
        $tenant = $location->tenant;
        $locationPaymentGateway = $this->getLocationPaymentGateway($location->getKey());

        if (! $locationPaymentGateway) {
            throw new RuntimeException("No Three Peaks payment credentials found or credentials are incorrect for $location->name.");
        }

        $debitBatch->startLogEntry();

        $userBatches = UserBatch::query()
            ->join('user_banking_details', function (JoinClause $join) use ($tenant) {
                $join->on('user_to_batch.user_id', '=', 'user_banking_details.user_id')
                    ->where('user_banking_details.box_id', $tenant->getKey())
                    ->where('user_banking_details.is_active', '=', true);
            })
            ->join('user_to_box', function (JoinClause $join) use ($tenant) {
                $join->on('user_to_batch.user_id', '=', 'user_to_box.user_id')
                    ->where('user_to_box.box_id', $tenant->getKey())
                    ->where('user_to_box.end_date', '>', today())
                    ->where('user_to_box.user_debit_status_id', '=', UserDebitStatus::DEBIT_ORDER)
                    ->where('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED)
                    ->where('user_to_box.deleted', '=', false);
            })
            ->where('user_to_batch.debit_batch_id', '=', $debitBatch->getKey())
            ->where('user_to_batch.amount_editable', '>', 0)
            ->where('user_to_batch.is_active', '=', true)
            ->whereHas('user')
            ->groupBy('user_to_batch.user_to_batch_id')
            ->get();

        if ($userBatches->isEmpty()) {
            $debitBatch->log("No user batches found for $location->name -> {$debitBatch->debitDayDate->date->format('Y-m-d')}.")->endLogEntry();

            throw new RuntimeException("Tried to submit debit batch ID {$debitBatch->getKey()} but all users where filtered out.");
        }

        $processDateOverride = $processDateOverride ?? $debitBatch->debitDayDate->date;

        // Build xml batch file and get batchData['user']
        $batchData = $this->buildDebitBatchFile($debitBatch, $userBatches, $processDateOverride);

        // Submit batch to Three peaks
        $this->sendBatch($locationPaymentGateway, $debitBatch, $batchData);

        $debitBatch->endLogEntry();

        $debitBatch->save();
    }

    public function reconcileDebitBatch(DebitBatch $debitBatch): void
    {
        if ($debitBatch->isNotProcessed()) {
            throw new ThreePeaksException("Trying to reconcile debit batch ID: {$debitBatch->getKey()} but is has not yet been processed.");
        }

        $location = $debitBatch->location;

        $userBatchesNeedingRecon = $debitBatch->userBatch()
            ->join('finance_invoices', 'user_to_batch.invoice_id', '=', 'finance_invoices.invoice_id')
            ->where('user_to_batch.is_active', true)
            ->where('finance_invoices.deleted', false)
            ->get();

        if (count($userBatchesNeedingRecon) <= 0) {
            throw new ThreePeaksException('No users found in the batch.');
        }

        $debitsInSubmission = $this->getDebitsInSubmission($location->getKey(), $debitBatch->subid);

        if (count($debitsInSubmission) <= 0) {
            throw new ThreePeaksException('No debits found in this submission.');
        }

        $debitBatch->startLogEntry();

        foreach ($userBatchesNeedingRecon as $userBatch) {
            $invoice = $userBatch->invoice;

            $debitInfo = $this->getUserDebitFromDebitsInSubmission($userBatch->user_id, $debitsInSubmission);

            // If no info found then skip;
            if (! is_array($debitInfo)) {
                continue;
            }

            $debiStatus = $debitInfo['debitStatus'];
            $debtorNote = $debitInfo['debtorNote'];

            // Skip if debit status is any of these statuses
            if (in_array($debiStatus, [ThreePeaksDebitStatus::WAITING_OR_CANCELLED, ThreePeaksDebitStatus::BANK_PRE_VALIDATION_ACCEPT, ThreePeaksDebitStatus::BANK_ACCEPT])) {
                continue;
            }

            $newInvoiceStatus = $debiStatus === ThreePeaksDebitStatus::FUNDS_COLLECTED ? InvoiceStatus::PAID : InvoiceStatus::UNPAID;

            // Status has NOT changed
            if ($newInvoiceStatus === $invoice->status) {
                continue;
            }

            if (in_array($debiStatus, [ThreePeaksDebitStatus::BANK_PRE_VALIDATION_REJECT, ThreePeaksDebitStatus::BANK_REJECT, ThreePeaksDebitStatus::FUNDS_UNPAID, ThreePeaksDebitStatus::FUNDS_LATE_UNPAID])) {
                $invoicePayments = $invoice->payments()->where('finance_payments.deleted', false);

                $debitPayments = $invoicePayments->where('finance_payments.type', '=', 'debit_order')->get();

                if (count($debitPayments) > 0) {
                    foreach ($debitPayments as $debitOrderPayment) {
                        $debitOrderPayment->update(['deleted' => true]);

                        $debitBatch->log("Removed payment from invoice: {$invoice->getKey()}");
                    }
                }

                $otherPayments = $invoicePayments->where('finance_payments.type', '!=', 'debit_order');

                if ($otherPayments->count() == 0 && (in_array($invoice->status, [InvoiceStatus::PENDING, InvoiceStatus::PAID]))) {
                    $invoice->update(['status' => InvoiceStatus::UNPAID]);

                    $debitBatch->log("Invoice #$invoice->code for {$userBatch->user->full_name} has been marked as <strong>unpaid</strong>. Debtor note: $debtorNote");
                }
            } elseif ($debiStatus == ThreePeaksDebitStatus::FUNDS_COLLECTED) {
                $paymentDate = Carbon::createFromFormat('d-m-Y', (string) $debitInfo['actionDate']);

                $invoice->update(['status' => InvoiceStatus::PAID]);
                $reference = 'Debit order payment - '.$paymentDate->format('Y-m-d');

                if ($invoice->getExistingPaymentsBy($userBatch->amount_editable, $reference, InvoicePaymentType::DEBIT_ORDER->value)->count() === 0) {
                    $payment = UserInvoicePayment::create([
                        'invoice_id' => $invoice->getKey(),
                        'user_to_facility_id' => $invoice->user_to_facility_id,
                        'amount' => $userBatch->amount_editable,
                        'currency' => $debitBatch->location->tenant->memberCurrency->code,
                        'date_time' => $invoice->due_on,
                        'type' => InvoicePaymentType::DEBIT_ORDER,
                        'reference' => $reference,
                    ]);

                    $tag = Tag::query()->paymentTag(PaymentProcessorTag::THREE_PEAKS)->first();

                    (new TagsService)->sync([$tag->getKey()], $payment);
                }

                $debitBatch->log("Invoice #$invoice->code for {$userBatch->user->full_name} has been marked as <strong>paid</strong>. Debtor note: $debtorNote");
            } else {
                $debitStatusString = $debiStatus->toString();
                $debitBatch->log("({$userBatch->getKey()}) Invoice #$invoice->code for {$userBatch->user?->full_name} did not receive any updated transactions. Three Peaks status: $debitStatusString.");
            }
        }

        $debitBatch->endLogEntry();

        $debitBatch->save();
    }

    public function recallSubmission(int $locationId, DebitBatch $debitBatch, string $message): array
    {
        $locationPaymentGateway = $this->getLocationPaymentGateway($locationId);
        $auth = $this->authenticate($locationPaymentGateway);

        $response = Http::asForm()
            ->withHeaders($this->getHeaders())
            ->post(config('threepeaks.api.url'), [
                'f' => 'Recall',
                'session' => $auth['session'],
                'cref' => $auth['reference'],
                'subid' => $debitBatch->subid,
                'message' => $message,
            ]);

        $response->throw();

        $xml = simplexml_load_string(trim($response->body()));

        if ($xml === false || ! isset($xml->recall)) {
            if (isset($xml->function)) {
                throw new ThreePeaksException((string) $xml->function->code->attributes()->{'note'});
            }

            throw new ThreePeaksException('XML format error listing submissions.');
        }

        $this->logout($locationPaymentGateway, $auth['session']);

        $debitBatch->update([
            'dt_processed' => null,
            'is_processed' => false,
        ]);

        $debitBatch->startLogEntry();
        $debitBatch->log('The following batch has been recalled: '.$debitBatch->getKey());
        $debitBatch->log('Recall date: '.now()->format('Y-m-d H:i:s'));
        $debitBatch->endLogEntry();

        $debitBatch->save();

        return [
            'code' => (string) $xml->recall->code,
            'note' => (string) $xml->recall->code->attributes()->{'note'},
        ];
    }

    public function getUserDebitFromDebitsInSubmission(int $userId, $debitsInSubmission): array|false
    {
        foreach ($debitsInSubmission as $debit) {
            if (isset($debit['debtorReference']) && ($userId !== $debit['debtorReference'])) {
                continue;
            }

            return Arr::only($debit, ['actionDate', 'debtorNote', 'debitStatus']);
        }

        return false;
    }

    private function authenticate(LocationPaymentGateway $locationPaymentGateway): array
    {
        $credentials = $locationPaymentGateway->getThreePeaksCredentials();

        $response = Http::retry(2, 150)
            ->asForm()
            ->withHeaders($this->getHeaders())
            ->post(config('threepeaks.api.url'), [
                'f' => 'auth',
                'devid' => $credentials['development_id'],
                'devtoken' => $credentials['development_token'],
            ]);

        $response->throw();

        try {
            $xml = simplexml_load_string(trim($response->body()));
        } catch (Exception) {
            $xml = false;
        }

        if ($xml === false) {
            throw new ThreePeaksAuthenticationException("XML invalid for location payment gateway ID: {$locationPaymentGateway->getKey()}");
        }

        if ((int) $xml->auth->code !== 10001) {
            throw new ThreePeaksAuthenticationException(
                "Authentication failed for payment gateway ID: {$locationPaymentGateway->getKey()} | ErrorCode: {$xml->auth->code} | ".$xml->auth->code->attributes()->{'note'}
            );
        }

        return [
            'session' => (string) $xml->auth->session,
            'reference' => $credentials['reference'],
        ];
    }

    private function logout(LocationPaymentGateway $locationPaymentGateway, $sessionToken): void
    {
        $credentials = $locationPaymentGateway->getThreePeaksCredentials();

        $response = Http::retry(3, 150)
            ->asForm()
            ->withHeaders($this->getHeaders())
            ->post(config('threepeaks.api.url'), [
                'f' => 'logout',
                'devid' => $credentials['development_id'],
                'devtoken' => $credentials['development_token'],
                'session' => $sessionToken,
            ]);

        $response->throw();
    }

    private function getLocationPaymentGateway(int $locationId): ?LocationPaymentGateway
    {
        $locationPaymentGateway = LocationPaymentGateway::query()
            ->where('box_facility_id', '=', $locationId)
            ->where('is_active', '=', true)
            ->where('context', '=', 'debit_order')
            ->where('payment_gateway_id', '=', PaymentGateway::THREE_PEAKS)
            ->orderBy('facility_to_payment_gateway_id', 'desc')
            ->first();

        if (! $locationPaymentGateway || strlen($locationPaymentGateway->credentials) < 10) {
            throw new RuntimeException('Payment gateway credentials not found or are incomplete');
        }

        return $locationPaymentGateway;
    }

    private function convertSubmissionInfoToArray($submissionInfo): array
    {
        if (! $submissionInfo) {
            return [];
        }

        $submissionStatus = $submissionInfo->substatus ?? $submissionInfo->status;

        return [
            'submissionId' => (string) $submissionInfo->subid,
            'actionDate' => $this->fixActionDate($submissionInfo->adate),
            'records' => (string) $submissionInfo->records,
            'amount' => (string) $submissionInfo->amount,
            'submissionStatusId' => (int) $submissionStatus,
            'submissionStatus' => ThreePeaksSubmissionStatus::from((int) $submissionStatus)->toString(),
            'rejectedRecords' => (string) $submissionInfo->rej_records,
            'rejectedAmount' => (string) $submissionInfo->rej_amount,
            'unpaidRecords' => (string) $submissionInfo->unpiad_records,
            'unpaidAmount' => (string) $submissionInfo->unpaid_amount,
            'lateUnpaidRecords' => (string) $submissionInfo->lunpaid_records,
            'lateUnpaidAmount' => (string) $submissionInfo->lunpaid_amount,
            'validationStatusId' => (int) $submissionInfo->apiprocessing,
            'validationStatus' => ThreePeaksApiProcessingStatus::from((int) $submissionInfo->apiprocessing)->toString(),
        ];
    }

    private function convertSubmissionDebitsToArray($debitInfo): array
    {
        if (! $debitInfo) {
            return [];
        }

        return [
            'submissionId' => (string) $debitInfo->subid,
            'reference' => (string) $debitInfo->rbr,
            'actionDate' => $this->fixActionDate($debitInfo->adate),
            'debtorReference' => (int) $debitInfo->debitref,
            'accountHolder' => (string) $debitInfo->accholder,
            'description' => (string) $debitInfo->description,
            'bankName' => (string) $debitInfo->bankname,
            'bankAccountNumber' => (string) $debitInfo->bankacc,
            'bankCode' => (string) $debitInfo->bankcode,
            'bankAccountType' => (string) $debitInfo->bankacctype,
            'amount' => (string) $debitInfo->amount,
            'idNo' => (string) $debitInfo->idno,
            'cell' => (string) $debitInfo->cell,
            'debtorNote' => (string) $debitInfo->hmessage,
            'retentionAmount' => (string) $debitInfo->retamount,
            'debitFee' => (string) $debitInfo->debitfee,
            'unpaidFee' => (string) $debitInfo->unpaidfee,
            'cdv' => ThreePeaksCheckDigitVerificationStatus::tryFrom((int) $debitInfo->cdv) ?? ThreePeaksCheckDigitVerificationStatus::NOT_DONE,
            'bankProcessingStatus' => ThreePeaksBankProcessingStatus::tryFrom((int) $debitInfo->bpro) ?? ThreePeaksBankProcessingStatus::NOT_DONE,
            'paidUnpaidStatus' => ThreePeaksPaidOrUnpaidStatus::tryFrom((int) $debitInfo->pu) ?? ThreePeaksPaidOrUnpaidStatus::NOT_DONE,
            'statusDescriptionOfDebit' => (string) $debitInfo->cpmessage,
            'bankStatus' => $this->getBankingStatus((int) $debitInfo->fstat),
            'breturn' => $this->getBankingStatus((int) $debitInfo->breturn),
            'debitStatus' => ThreePeaksDebitStatus::tryFrom((int) $debitInfo->debitstatus) ?? ThreePeaksDebitStatus::WAITING_OR_CANCELLED,
        ];
    }

    private function getBankingStatus($statusNumber): string
    {
        return match ($statusNumber) {
            'XX' => 'Transaction Processed',
            'UU', 'RR' => 'Unpaid or Late Unpaid',
            default => 'No Transaction/Rejected',
        };
    }

    private function fixActionDate($actionDate): string
    {
        // Add hyphens to the date string
        return substr((string) $actionDate, 0, 2).'-'.substr((string) $actionDate, 2,
            2).'-'.substr((string) $actionDate, 4, 4);
    }

    private function buildDebitBatchFile(DebitBatch $debitBatch, Collection $userBatches, Carbon $date): array
    {
        $batchSum = $userBatches->sum('amount');

        $format = '<debit>';
        $format .= '<adate>'.$date->format('dmY').'</adate>';
        $format .= '<debitref>%s</debitref>';
        $format .= '<accholder>%s</accholder>';
        $format .= '<description>%s</description>';
        $format .= '<bankname>%s</bankname>';
        $format .= '<bankacc>%s</bankacc>';
        $format .= '<bankbranch>Universal</bankbranch>';
        $format .= '<bankcode>%s</bankcode>';
        $format .= '<bankacctype>0</bankacctype>';
        $format .= '<amount>%s</amount>';
        $format .= '<cell>%s</cell>';
        $format .= '</debit>';

        $xmlData = $userBatches->map(function ($userBatch) use ($format, $debitBatch) {
            $debitBatch->log("Adding user to batch: {$userBatch->user->email} [$userBatch->amount]");

            $bankingDetails = UserBankingDetail::query()
                ->where('user_id', '=', $userBatch->user_id)
                ->where('box_id', '=', $userBatch->debitBatch->location->tenant->getKey())
                ->where('is_active', true)
                ->first();

            $accountName = preg_replace('/[^A-Za-z0-9 ]/', '', trim(substr($userBatch->user->full_name, 0, 30)));
            $accountHolder = preg_replace('/[^A-Za-z0-9 ]/', '', trim(substr($bankingDetails->account_name, 0, 30)));
            $mobile = $userBatch->user->mobile ? str_replace(' ', '', $userBatch->user->mobile) : null;

            return sprintf($format,
                str_pad($userBatch->user_id, 10, '0', STR_PAD_LEFT),
                htmlentities($accountName),
                htmlentities($accountHolder),
                $bankingDetails->bank->bank_name,
                $bankingDetails->account_no,
                $bankingDetails->bank->universal_code,
                $userBatch->amount_editable,
                htmlentities($mobile),
            );
        })->join('');

        $debitBatch->log("Total amount: [$batchSum]");

        $xmlStart = "<?xml version='1.0' encoding='windows-1253'?>".'<newsub>';
        $xmlEnd = '</newsub>';
        $fullXml = simplexml_load_string($xmlStart.$xmlData.$xmlEnd)->asXML();

        $batchFilename = 'octiv-batches/three-peaks/Batch_'.$debitBatch->getKey().'_'.$date->format('Ymd').'_'.time().'.xml';

        Storage::disk('private')->put($batchFilename, $fullXml);
        $debitBatch->file = Storage::url($batchFilename);

        $debitBatch->log('Successfully written batch file to file system');
        $debitBatch->save();

        return [
            'batchSum' => $batchSum,
            'batchDate' => $date->format('dmY'),
            'batchUserCount' => count($userBatches),
            'batchFileContents' => $fullXml,
        ];
    }

    private function sendBatch(LocationPaymentGateway $locationPaymentGateway, DebitBatch $debitBatch, array $batchData): void
    {
        try {
            $auth = $this->authenticate($locationPaymentGateway);

            if (isset($auth['session'])) {
                $response = Http::asForm()
                    ->withHeaders($this->getHeaders())
                    ->post(config('threepeaks.api.url'), [
                        'f' => 'newsub',
                        'session' => $auth['session'],
                        'cref' => $auth['reference'],
                        'adate' => $batchData['batchDate'],
                        'records' => $batchData['batchUserCount'],
                        'amount' => $batchData['batchSum'],
                        'xmltype' => 0,
                        'xml' => $batchData['batchFileContents'],
                        'allowRollOverdate' => 1,
                        'allowDataAlteration' => 1,
                    ]);

                try {
                    $response->throw();

                    $xml = simplexml_load_string(trim($response->body()));

                    if ($xml === false) {
                        throw new ThreePeaksException("XML format error submitting batch to Three Peaks, batch ID: {$debitBatch->getKey()}");
                    }

                    // Check if submission was valid
                    if (isset($xml->sub)) {
                        $errors = [];

                        if ((int) $xml->sub->adate !== 10220) {
                            $errors[] = 'Action date: '.$xml->sub->adate->attributes()->{'note'};
                        }

                        if ((int) $xml->sub->xml !== 1) {
                            $errors[] = 'Xml: '.$xml->sub->xml->attributes()->{'note'};
                        }

                        if ((int) $xml->sub->records !== 1) {
                            $errors[] = 'Records: '.$xml->sub->records->attributes()->{'note'};
                        }

                        if ((int) $xml->sub->amount !== 1) {
                            $errors[] = 'Amount: '.$xml->sub->amount->attributes()->{'note'};
                        }

                        if ((int) $xml->sub->subid === 0) {
                            $errors[] = 'Sub id: '.$xml->sub->subid->attributes()->{'note'};
                        }

                        if (count($errors) > 0) {
                            throw new ThreePeaksException("Batch file has the following errors: \n\n".implode(",\n",
                                $errors));
                        }
                    }

                    $debitBatch->log('Batch submission response: '.$response->body());

                    // Set the submission id on debit batch
                    $debitBatch->update([
                        'subid' => (int) $xml->sub->subid,
                        'is_processed' => true,
                        'dt_processed' => now(),
                    ]);
                } catch (RequestException|ThreePeaksException $e) {
                    // Send and log error
                    $errorMessage = 'Batch submission error: '.$e->getMessage();
                    $subject = 'Batch submission error: '.$debitBatch->location->name;

                    $this->sendErrorMail($debitBatch, $subject, $errorMessage);
                    $debitBatch->log($errorMessage);

                    // Status indicates failure
                    $debitBatch->is_processed = false;
                }
            } else {
                throw new ThreePeaksAuthenticationException("XML invalid for location payment gateway ID: {$locationPaymentGateway->getKey()}");
            }
        } catch (ThreePeaksAuthenticationException $e) {
            // Send and log error
            $error = 'API Authentication error: '.$e->getMessage();
            $subject = 'Authentication error: '.$debitBatch->location->name;

            $this->sendErrorMail($debitBatch, $subject, $error);

            $debitBatch->log('3 Peaks response: '.$e->getMessage());

            // Status indicates failure
            $debitBatch->is_processed = false;
        }
    }

    private function sendErrorMail(DebitBatch $debitBatch, string $subject, string $errorMessage): void
    {
        $crm = resolve(CrmService::class);

        $headCoachList = TenantUser::query()
            ->headCoaches()
            ->active()
            ->where('box_id', $debitBatch->location->tenant_id)
            ->get();

        $message = 'Hi,<br /><br />';
        $message .= 'Your debit batch ('.$debitBatch->debitDayDate->date->toDateString().') was not submitted because of the error below:<br /><br />';
        $message .= 'Error: '.$errorMessage.'<br /><br />';
        $message .= 'Please note that you will need to resubmit the batch by going to Reports -> Resubmit batches.';

        /** @var User $headCoach */
        foreach ($headCoachList as $headCoach) {
            $crm->createScheduledEmail($message, $subject, $headCoach->user->email);
        }

        // Notification sent to the dev team
        $message .= '<br /><br />Batch file: '.$debitBatch->debit_batch_filename;
        $crm->createScheduledEmail($message, $subject, 'support@octivfitness.com');
    }
}
