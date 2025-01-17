<?php

namespace App\Services\PaymentGateways;

use App\Enums\AccountType;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Enums\PaymentProcessorTag;
use App\Models\DebitBatch;
use App\Models\DebitBatchStatement;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\Tag;
use App\Models\TenantUser;
use App\Models\UserBatch;
use App\Models\UserInvoicePayment;
use App\Services\CrmService;
use App\Services\DebitBatchService;
use App\Services\FinanceService;
use App\Services\TagsService;
use App\Services\TenantUserService;
use App\Services\UserBatchService;
use DateInterval;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SoapClient;
use SoapHeader;

class NetcashService
{
    const SUBMISSION_TYPE_TWO_DAY = 'TwoDay';

    const SUBMISSION_TYPE_SAME_DAY = 'SameDay';

    public function validateServiceKeys(string $merchantAccountNumber, array $keys): array
    {
        $params = [
            'MerchantAccount' => $merchantAccountNumber,
            'SoftwareVendorKey' => config('netcash.api.software_vendor_key'),
            'ServiceInfoList' => $keys,
        ];

        $client = new SoapClient('https://ws.netcash.co.za/niws/niws_partner.svc?wsdl', [
            'soap_version' => SOAP_1_2,
            'trace' => 1,
            'exceptions' => 0,
        ]);

        $actionHeader = new SoapHeader('http://www.w3.org/2005/08/addressing', 'Action', 'http://tempuri.org/INIWS_Partner/ValidateServiceKey');
        $toHeader = new SoapHeader('http://www.w3.org/2005/08/addressing', 'To', 'https://ws.netcash.co.za/NIWS/NIWS_Partner.svc');

        $client->__setSoapHeaders([
            $actionHeader,
            $toHeader,
        ]);

        $result = $client->ValidateServiceKey(['request' => $params]);
        $success = false;
        $message = null;

        if (isset($result->ValidateServiceKeyResult)) {
            $serviceKeyResult = $result->ValidateServiceKeyResult;
            $statusCode = $serviceKeyResult->AccountStatus;

            switch ($statusCode) {
                case '001':
                    $success = true;
                    $message = 'Successfully validated service keys';
                    break;
                case '104':
                    $message = 'Your customer number could not be validated. Please contact your Netcash account manager for assistance.';
                    break;
                case '201':
                    $message = 'You have been locked out of your Netcash account. Please contact your Netcash account manager for assistance.';
                    break;
                default:
                    $message = 'Your service keys could not be validated due to an unknown issue. Please contact support.';
                    break;
            }
        }

        return [
            'success' => $success,
            'message' => $message,
        ];
    }

    private function buildBatchFile(string $serviceKey, DebitBatch $debitBatch, Collection $userBatches, ?Carbon $processDateOverride = null, string $type = 'TwoDay'): ?string
    {
        $batchId = $debitBatch->getKey();

        $batchDate = ($processDateOverride !== null ? $processDateOverride : Carbon::now()->add(new DateInterval($debitBatch->debitDayDate->debitDay->interval)))->format('Ymd');
        $batchFilename = 'Batch_'.$batchId.'_'.$batchDate.'.txt';

        // create file for writing
        $fileHandle = fopen('php://temp', 'w+');

        if (! $fileHandle) {
            $debitBatch->log('Could not open file on S3 for writing batch content.');

            return null;
        }

        // HEADER
        fputcsv($fileHandle, [
            'H',
            $serviceKey,
            '1',
            $type,
            $batchId,
            $batchDate,
            config('netcash.api.software_vendor_key'),
        ], "\t");

        // KEYS
        fputcsv($fileHandle, [
            'K',
            '101', // account ref
            '102', // account holder name
            '131', // banking detail type = 1
            '132', // account holder name (again)
            '133', // account type
            '134', // branch code - must be 6 digits (leading 0s)
            '135', // filler (default 0)
            '136', // bank account number
            '161', // default amount
            '162', // payment amount
            '201', // client email address
            '301', // custom data 1
        ], "\t");

        // TRANSACTIONS
        $total = 0;

        /** @var UserBatch $userBatch */
        foreach ($userBatches as $userBatch) {
            $debitBatch->log("Adding user to batch: {$userBatch->user->email} [$userBatch->amount_in_cents]");
            $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($userBatch->user, $userBatch->debitBatch->location->tenant);

            if (! $userBoxMembership instanceof TenantUser) {
                continue;
            }

            fwrite($fileHandle, implode("\t", $this->buildBatchEntry($userBatch, $userBoxMembership))."\n");

            $total += $userBatch->amount_in_cents;
        }

        // FOOTER
        fputcsv($fileHandle, [
            'F',
            $userBatches->count(), // number of transactions
            $total, // grand total
            '9999', // end of file delimiter
        ], "\t");

        $debitBatch->log("Added footer to content: [$total]");

        rewind($fileHandle);
        $fileContents = stream_get_contents($fileHandle);

        Storage::disk('private')->put('Netcash/octiv-batches/'.$batchFilename, $fileContents);
        $path = Storage::disk('private')->url('Netcash/octiv-batches/'.$batchFilename);
        $debitBatch->log("File location s3: [$path]");

        return $fileContents;
    }

    private function buildBatchEntry(UserBatch $userBatch, TenantUser $userBoxMembership): array
    {
        $bankDetails = $userBoxMembership->bankAccount;

        return [
            'T',
            str_pad($userBatch->user->getKey(), 2, '0', STR_PAD_LEFT), // account ref = userId
            $userBatch->user->full_name, // account holder name = user full name
            '1', // banking detail type = 1
            $userBatch->user->full_name, // account holder name (again)
            $bankDetails->account_type_id->value ?? null, // account type
            $bankDetails->bank->universal_code ? $bankDetails->bank->universal_code_formatted_for_netcash_batch : $bankDetails->format_branch_code_for_netcash_batch, // branch code - 6 digits
            '0', // filter (default 0)
            trim($bankDetails->account_no), // account number
            $userBatch->amount_in_cents, // default amount
            $userBatch->amount_in_cents, // payment amount
            trim($userBatch->user->email), // user email
            $userBatch->getKey(), // custom data 1
        ];
    }

    private function sendBatch($serviceKey, $batchContent): ?string
    {
        try {
            $soap = new SoapClient('https://ws.netcash.co.za/NIWS/niws_nif.svc?wsdl');

            $params = [
                'ServiceKey' => $serviceKey,
                'File' => $batchContent,
            ];

            // Send the request
            $result = $soap->BatchFileUpload($params);

            return $result->BatchFileUploadResult;
        } catch (Exception $ex) {
            (new CrmService())->createScheduledEmail(
                content: 'A batch to Netcash has just thrown an exception. '.$ex->getMessage().'<br><br>'.$batchContent,
                subject: 'Netcash batch failed',
                to: 'tech@octivfitness.com',
            );

            return null;
        }
    }

    public function validateBankingDetails(Location $location, $accountNumber, $branchCode, AccountType $accountType): bool|string
    {
        $locationPaymentGateway = $this->getNetcashLocationPaymentGateway($location);

        if (! $location->can_debit || ! $locationPaymentGateway || $locationPaymentGateway->payment_gateway_id !== PaymentGateway::SAGE_PAY_V3->value) {
            return true;
        }

        if (! $locationPaymentGateway || ! $locationPaymentGateway->credentials) {
            Log::error("Location payment gateway not found or empty credentials for: {$location->name}", [
                'location_id' => $location->getKey(),
                'location_payment_gateway' => $locationPaymentGateway?->getKey(),
            ]);

            return 'Location payment gateway credentials not found.';
        }

        $credentials = explode('&', $locationPaymentGateway->credentials);

        if (! $serviceKey = Arr::get($credentials, '1')) {

            Log::error("Sage service key not found for location: {$location->name}", [
                'location_payment_gateway' => $locationPaymentGateway->getKey(),
            ]);

            return 'Service key not found.';
        }

        $soap = new SoapClient('https://ws.netcash.co.za/NIWS/niws_validation.svc?wsdl');

        $params = [
            'ServiceKey' => $serviceKey,
            'BranchCode' => $branchCode,
            'AccountType' => $accountType->value,
            'AccountNumber' => $accountNumber,
        ];

        // Send the request
        $result = $soap->ValidateBankAccount($params);
        $resultInt = (int) $result->ValidateBankAccountResult;

        if ($resultInt === 0) {
            return true;
        } elseif ($resultInt === 1) {
            return 'Invalid branch code.';
        } elseif ($resultInt === 2) {
            return 'Account number failed check digit validation.';
        } elseif ($resultInt === 3) {
            return 'Invalid account type.';
        } elseif ($resultInt === 4) {
            return 'Input data incorrect';
        } elseif ($resultInt === 100) {
            return 'Authentication failed';
        } else {
            return 'Web service error contact support@sagepay.co.za.';
        }
    }

    public function requestNetcashStatement(DebitBatch $debitBatch, Carbon $date): bool|string
    {
        $locationPaymentGateway = $this->getNetcashLocationPaymentGateway($debitBatch->location);

        if (! $locationPaymentGateway) {
            Log::warning("No payment gateway debit batch {$debitBatch->getKey()} ({$debitBatch->location->name})");

            return "No payment gateway debit batch {$debitBatch->getKey()} ({$debitBatch->location->name})";
        }

        $debitBatchStatement = (new DebitBatchService())->getDebitBatchStatementForDebitBatchAndDate($debitBatch, $date);

        if ($debitBatchStatement) {
            Log::warning("Statement already exists for debit batch {$debitBatch->getKey()} ({$debitBatch->location->name})");

            return "Statement already exists for debit batch {$debitBatch->getKey()} ({$debitBatch->location->name})";
        }

        $dateAsString = $date->format('Ymd');
        $credentials = explode('&', $locationPaymentGateway->credentials);
        $serviceKey = $credentials[1];

        $soap = new SoapClient('https://ws.netcash.co.za/NIWS/niws_nif.svc?wsdl');

        $params = [
            'ServiceKey' => $serviceKey,
            'FromActionDate' => $dateAsString,
        ];

        // Send the request
        $result = $soap->RequestMerchantStatement($params);

        if ($result) {
            $result = $result->RequestMerchantStatementResult;

            if ($result == '100' || $result == '101' || $result == '102' || $result == '200') {
                Log::warning("Request statement failed. Debit batch: {$debitBatch->getKey()} with date {$date->format('Y-m-d')} for facility {$debitBatch->location->name} failed with error: $result");

                return "Request statement failed. Debit batch: {$debitBatch->getKey()} with date {$date->format('Y-m-d')} for facility {$debitBatch->location->name} failed with error: $result";
            }

            DebitBatchStatement::query()->create([
                'debit_batch_id' => $debitBatch->getKey(),
                'polling_id' => (string) $result,
                'dt_requested' => now(),
                'dt' => $date,
            ]);
        }

        return true;
    }

    public function attemptDownloadNetcashStatement(DebitBatchStatement $statement): bool|string
    {
        $locationPaymentGateway = $this->getNetcashLocationPaymentGateway($statement->debitBatch->location);

        if (! $locationPaymentGateway) {
            Log::warning("No payment gateway for {$statement->debitBatch->location->name}");

            return "No payment gateway for {$statement->debitBatch->location->name}";
        }

        $credentials = explode('&', $locationPaymentGateway->credentials);
        $serviceKey = $credentials[1];

        $soap = new SoapClient('https://ws.netcash.co.za/NIWS/niws_nif.svc?wsdl');

        $params = [
            'ServiceKey' => $serviceKey,
            'PollingId' => $statement->polling_id,
        ];

        // Send the request
        $result = $soap->RetrieveMerchantStatement($params);

        if ($result == '100' || $result == '101' || $result == '102' || $result == '200') {
            Log::warning("Download statement failed. Statement ID: {$statement->getKey()} for debit batch {$statement->debitBatch->getKey()} failed with error: $result");

            return "Download statement failed. Statement ID: {$statement->getKey()} for debit batch {$statement->debitBatch->getKey()} failed with error: $result";
        }

        $statement->update([
            'content' => $result->RetrieveMerchantStatementResult,
            'dt_downloaded' => now(),
        ]);

        return true;
    }

    public function submitNetcashBatch(DebitBatch $debitBatch, ?Carbon $processDateOverride = null, ?string $typeOverride = self::SUBMISSION_TYPE_SAME_DAY): bool|string|array
    {
        $locationPaymentGateway = $this->getNetcashLocationPaymentGateway($debitBatch->location);

        if (! $locationPaymentGateway instanceof LocationPaymentGateway) {
            Log::warning("{$debitBatch->location->name} has no active payment gateway.");

            return "{$debitBatch->location->name} has no active payment gateway.";
        }

        if (strlen($locationPaymentGateway->credentials) <= 10 || ! $locationPaymentGateway->getServiceKeyCredential()) {
            Log::warning("{$locationPaymentGateway->location->name} appears to have invalid payment gateway credentials. BoxFacilityPaymentGatewayId={$locationPaymentGateway->getKey()}");

            return "{$locationPaymentGateway->location->name} appears to have invalid payment gateway credentials. BoxFacilityPaymentGatewayId={$locationPaymentGateway->getKey()}";
        }

        $userBatches = (new UserBatchService())->getUserBatchesForDebitBatchSubmission($debitBatch);

        if ($userBatches->isEmpty()) {
            Log::warning("Debit batch {$debitBatch->getKey()} appears to have no user batches for submission.");

            return "Debit batch {$debitBatch->getKey()} appears to have no user batches for submission.";
        }

        if (! $this->validateUserBatches($debitBatch, $userBatches)) {
            return 'Some users on the debit batch does not have valid banking details.';
        }

        if (! $typeOverride) {
            $type = $debitBatch->debitDayDate->debitDay->interval === 'P0D' ? self::SUBMISSION_TYPE_SAME_DAY : self::SUBMISSION_TYPE_TWO_DAY;
        } else {
            $type = $typeOverride;
        }

        $debitBatch->startLogEntry();

        $batchContent = $this->buildBatchFile($locationPaymentGateway->getServiceKeyCredential() ?? null, $debitBatch, $userBatches, $processDateOverride, $type);

        $debitBatch
            ->log('========== start batch content ==========')
            ->log($batchContent)
            ->log('========== end batch content ==========');

        if (! $batchContent) {
            $debitBatch->log("Debit batch {$debitBatch->getKey()} file content could not be created/stored.")->endLogEntry();
            $debitBatch->save();

            return "Debit batch {$debitBatch->getKey()} file content could not be created/stored.";
        }

        $debitBatch->log("Send return the following result: $batchContent");

        $batchSubmitResult = false;

        if (app()->environment(['production'])) {
            $batchSubmitResult = $this->sendBatch($locationPaymentGateway->getServiceKeyCredential() ?? null, $batchContent);
        }

        if (! $batchSubmitResult) {
            $debitBatch->log("FAILED: Debit batch with ID of {$debitBatch->getKey()} failed during submission to Netcash due to Exception")->endLogEntry();
            $debitBatch->save();

            return 'Debit batch failed during submission to Netcash';
        } elseif ($batchSubmitResult == '100' || $batchSubmitResult == '101' || $batchSubmitResult == '102' || $batchSubmitResult == '200') {
            $netcashErrorsCodesAndMessage = [
                '100' => 'Netcash authentication failure, please verify your Netcash keys.',
                '101' => 'Date format error. If the string contains a date, it should be in the format CCYYMMDD.',
                '102' => 'Parameter error. One or more of the parameters in the string is incorrect.',
                '200' => 'General code exception. Please contact Netcash Technical Support.',
            ];

            $errorMessage = "Debit batch with ID of {$debitBatch->getKey()} failed during submission to Netcash with the following error message: ".$netcashErrorsCodesAndMessage[$batchSubmitResult];

            $emailContent = 'Facility: '.$debitBatch->location->tenant->name.'<br>';
            $emailContent .= 'Location: '.$debitBatch->location->name.'<br>';
            $emailContent .= 'Debit batch date: '.$debitBatch->debitDayDate->debit_day_date->format('Y-m-d').'<br><br>';
            $emailContent .= $errorMessage.'<br><br>';

            (new CrmService())->createScheduledEmail(
                content: $emailContent,
                subject: 'Netcash batch failed',
                to: 'support@octivfitness.com',
            );

            $debitBatch->log("FAILED: $errorMessage")->endLogEntry();
            $debitBatch->save();

            return 'Debit batch failed during submission to Netcash';
        }

        $debitBatch->update([
            'report_token' => $batchSubmitResult,
            'dt_processed' => now(),
            'is_processed' => true,
        ]);

        $debitBatch->log('Successful')->endLogEntry();
        $debitBatch->save();

        return true;
    }

    public function reconcileNetcashStatement(DebitBatchStatement $statement): bool
    {
        $statement->update([
            'dt_reconciled' => now(),
        ]);

        $payments = [];
        $tagIds = [Tag::query()->paymentTag(PaymentProcessorTag::NETCASH)->first()->getKey()];

        foreach (explode("\n", $statement->content) as $line) {
            $data = preg_split('/[\t]/', $line);

            if (! isset($data[1]) || empty($data[1]) || ! isset($data[7]) || empty($data[7])) {
                continue;
            }

            // If line doesn't contain a transaction worthy of reconciling, disregard.
            if (! in_array($data[1], ['TDD', 'SDD', 'DRU'])) {
                continue;
            }

            $userToBatch = UserBatch::query()->find($data[7]);

            if (! $userToBatch || ! $userToBatch->invoice) {
                Log::info("No user batch or invoice for line: $line");

                continue;
            }

            if ($data[1] == 'SDD' || $data[1] == 'TDD') {
                $reference = 'Debit order payment - '.Carbon::parse($data[0])->toDateString();

                if ($userToBatch instanceof UserBatch && $userToBatch->invoice->getExistingPaymentsBy($userToBatch->amount, $reference, InvoicePaymentType::DEBIT_ORDER->value)->count() === 0) {
                    $tenant = $userToBatch->debitBatch->location->tenant;

                    $payment = UserInvoicePayment::query()->create([
                        'invoice_id' => $userToBatch->invoice->getKey(),
                        'user_to_facility_id' => $userToBatch->invoice->user_location_id,
                        'amount' => $userToBatch->amount,
                        'currency' => $tenant->memberCurrency->code,
                        'date_time' => now(),
                        'type' => InvoicePaymentType::DEBIT_ORDER,
                        'reference' => $reference,
                    ]);

                    // Add payment and mark invoice as paid
                    $userToBatch->invoice->update(['status' => InvoiceStatus::PAID]);

                    $payments[] = $payment;

                    Log::info("Added payment for invoice: $userToBatch->invoice_id");
                }
            } elseif ($data[1] == 'DRU') {
                $debitPayments = (new FinanceService())->getPaymentsForInvoiceByType($userToBatch->invoice, InvoicePaymentType::DEBIT_ORDER->value);
                $otherPayments = (new FinanceService())->getPaymentsForInvoiceByOtherThanDebitOrderType($userToBatch->invoice);

                if (count($debitPayments) > 0) {
                    foreach ($debitPayments as $payment) {
                        $payment->update(['deleted' => true]);
                    }

                    Log::info("Removed payment from invoice: {$userToBatch->invoice->getKey()}");
                }

                if (count($otherPayments) == 0 && ($userToBatch->invoice->status == InvoiceStatus::PENDING || $userToBatch->invoice->status == InvoiceStatus::PAID)) {
                    $userToBatch->invoice->update(['status' => InvoiceStatus::UNPAID]);

                    Log::info("Set invoice to unpaid: {$userToBatch->invoice->getKey()}");
                }
            }
        }

        foreach ($payments as $payment) {
            (new TagsService())->sync($tagIds, $payment, auth()->user()?->getAuthIdentifier());
        }

        return true;
    }

    private function validateUserBatches(DebitBatch $debitBatch, Collection|array $userBatches): bool
    {
        $isUserBatchesValid = true;

        foreach ($userBatches as $userBatch) {
            $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($userBatch->user, $userBatch->debitBatch->location->tenant);

            if (! $tenantUser instanceof TenantUser) {
                Log::error("{$userBatch->getKey()} ({$userBatch->user->full_name}) does not have a valid facility membership.");
                $isUserBatchesValid = false;
            }

            if ($tenantUser instanceof TenantUser && ! $tenantUser->bankAccount) {
                Log::error("{$userBatch->getKey()} ({$userBatch->user->full_name}) does not have valid banking details.");
                $isUserBatchesValid = false;
            }

        }

        if (! $isUserBatchesValid) {
            Log::error("Some users do not have valid banking details on debit batch: {$debitBatch->getKey()}.");
        }

        return $isUserBatchesValid;
    }

    private function getNetcashLocationPaymentGateway(Location $location): ?LocationPaymentGateway
    {
        return LocationPaymentGateway::query()
            ->where('box_facility_id', '=', $location->getKey())
            ->where('is_active', '=', true)
            ->where('context', '=', PaymentGatewayContext::DEBIT_ORDER)
            ->where('payment_gateway_id', '=', PaymentGateway::SAGE_PAY_V3)
            ->orderBy('facility_to_payment_gateway_id', 'DESC')
            ->first();
    }
}
