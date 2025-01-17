<?php

namespace App\Http\Controllers\API;

use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DebitBatch\EnsureUserOnBatchRequest;
use App\Http\Requests\DebitBatch\ExportDebitBatchRequest;
use App\Http\Requests\DebitBatch\GetDebitBatchesForLocationRequest;
use App\Http\Requests\DebitBatch\GetDebitBatchesForTenantRequest;
use App\Http\Requests\DebitBatch\MarkDebitBatchAsUnprocessedRequest;
use App\Http\Requests\DebitBatch\ReconcileSepaBankStatementRequest;
use App\Http\Requests\DebitBatch\ReconcileThreePeaksBatchRequest;
use App\Http\Requests\DebitBatch\RegenerateDebitBatchInvoicesRequest;
use App\Http\Requests\DebitBatch\RequestNetcashStatementRequest;
use App\Http\Requests\DebitBatch\ResubmitDebitBatchRequest;
use App\Http\Requests\DebitBatch\ThreePeaksAllDebitsInSubmissionRequest;
use App\Http\Requests\DebitBatch\ThreePeaksListSubmissionsRequest;
use App\Http\Requests\DebitBatch\ThreePeaksRecallSubmissionRequest;
use App\Http\Requests\DebitBatch\ThreePeaksSubmissionInformationRequest;
use App\Http\Requests\DebitBatch\ThreePeaksValidateInformationRequest;
use App\Http\Resources\DebitBatchResource;
use App\Jobs\ThreePeaks\ReconcileBatch;
use App\Models\DebitBatch;
use App\Models\Location;
use App\Models\LocationUser;
use App\Models\TenantUser;
use App\Models\UserBatch;
use App\Models\UserInvoice;
use App\Services\DebitBatchService;
use App\Services\FinanceService;
use App\Services\InvoiceService;
use App\Services\PaymentGateways\NetcashService;
use App\Services\PaymentGateways\ThreePeaksService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class DebitBatchController extends Controller
{
    public function __construct(protected DebitBatchService $debitBatchService, protected InvoiceService $invoiceService, protected ThreePeaksService $threePeaksService, protected FinanceService $financeService)
    {
    }

    #[QueryParam('filter[date]', 'string', required: true)]
    #[QueryParam('filter[region_id]', 'integer', required: false)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[payment_gateway_id]', 'integer', required: false)]
    #[QueryParam('filter[search]', 'string', required: false)]
    #[QueryParam('page', 'string', required: false)]
    #[QueryParam('per_page', 'string', required: false)]
    public function getDebitBatchesForTenants(GetDebitBatchesForTenantRequest $request)
    {
        return DebitBatchResource::collection(QueryBuilder::for(DebitBatch::class)
            ->join('box_facility', 'debit_batches.box_facility_id', 'box_facility.box_facility_id')
            ->join('boxes', 'box_facility.box_id', 'boxes.box_id')
            ->where('box_facility.is_active', '=', true)
            ->where('boxes.box_status_id', '=', 1)
            ->where('box_facility.payment_gateway_id', '!=', PaymentGateway::NO_GATEWAY)
            ->allowedFilters([
                AllowedFilter::exact('date', 'debitDayDate.debit_day_date'),
                AllowedFilter::exact('region_id', 'boxes.region_id'),
                AllowedFilter::exact('location_id', 'box_facility.box_facility_id'),
                AllowedFilter::exact('payment_gateway_id', 'box_facility.payment_gateway_id'),
                AllowedFilter::partial('search', 'box_facility.box_facility_name'),
            ])
            ->allowedIncludes('location.paymentGateway')
            ->_paginate());
    }

    #[QueryParam('filter[location_id]', 'integer', required: true)]
    #[QueryParam('is_return_all', 'string', required: false)]
    #[QueryParam('page', 'string', required: false)]
    #[QueryParam('per_page', 'string', required: false)]
    public function getDebitBatchesForLocation(GetDebitBatchesForLocationRequest $request)
    {
        return DebitBatchResource::collection(QueryBuilder::for(DebitBatch::class)
            ->join('box_facility', 'debit_batches.box_facility_id', 'box_facility.box_facility_id')
            ->join('debit_day_dates', 'debit_batches.debit_day_date_id', 'debit_day_dates.debit_day_date_id')
            ->allowedFilters([
                AllowedFilter::exact('location_id', 'box_facility.box_facility_id'),
            ])
            ->where('box_facility.is_active', '=', true)
            ->whereBetween('debit_day_dates.debit_day_date', [now()->subMonth()->startOfMonth(), now()->addMonthNoOverflow()->endOfMonth()])
            ->when(! $request->is_return_all, function (Builder $query) {
                $query->where('debit_batches.debit_batch_total', '>', 0);
            })
            ->orderBy('debit_day_dates.debit_day_date')
            ->with(['location', 'debitDayDate'])
            ->_paginate());
    }

    #[QueryParam('pain_format', 'string', "Only for SEPA: Formats accepted = 'pain.008.001.02' OR 'pain.008.002.02' OR 'pain.008.003.02'. Default = 'pain.008.002.02'", false)]
    public function export(ExportDebitBatchRequest $request, DebitBatch $debitBatch)
    {
        $location = $debitBatch->location;

        if ($location->tenant->region->name !== 'Namibia' && ($location->payment_gateway_id !== PaymentGateway::SEPA->value || ! $location->paymentGateway->is_active)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Access denied. This is only for Namibian and SEPA facilities');
        }

        if ($location->tenant->region->name === 'Namibia') {
            // Check if the settings have been added
            if (! Arr::exists($location->extra_parameters, 'bank_user_code') || ! Arr::exists($location->extra_parameters, 'bank_user_name') || ! Arr::exists($location->extra_parameters, 'bank_nominated_account')) {
                abort(Response::HTTP_BAD_REQUEST, 'Please make sure that your export parameters have been set.');
            }

            $content = (new DebitBatchService())->generateNamibianDebitBatchExportContent($debitBatch);

            $filePath = 'debit-batch-exports/'.$debitBatch->getKey().'_export.txt';
        } else {
            $painFormat = $request->input('pain_format') ?? 'pain.008.002.02';
            $content = (new DebitBatchService())->generateSepaDebitBatchExportContent($debitBatch, $painFormat, boolval($request->input('is_process_as_batch')));

            $filePath = str('debit-batch-exports/')
                ->append($debitBatch->getKey())
                ->append('/')
                ->append($debitBatch->debitDayDate->date->toDateString())
                ->append('_')
                ->append(Str::upper(Str::replace('.', '_', $painFormat)))
                ->append('.xml')
                ->toString();
        }

        $debitBatch->update([
            'is_processed' => true,
            'dt_processed' => now(),
        ]);

        Storage::disk('tmp')->put($filePath, $content);

        return response()->json(Storage::disk('tmp')->temporaryUrl($filePath, now()->addMinutes(15)));
    }

    public function threePeaksListSubmissions(ThreePeaksListSubmissionsRequest $request, Location $location)
    {
        try {
            return response($this->threePeaksService->listSubmissions($location->getKey()));
        } catch (Exception $e) {
            abort(400, $e->getMessage());
        }
    }

    public function threePeaksSubmissionInformation(ThreePeaksSubmissionInformationRequest $request, DebitBatch $debitBatch)
    {
        if (! $debitBatch->subid) {
            abort(400, 'This debit batch does not have a submission id');
        }

        try {
            return response($this->threePeaksService->getSubmissionInformation(locationId: $debitBatch->location_id, submissionId: $debitBatch->subid));
        } catch (Exception $e) {
            abort(400, $e->getMessage());
        }
    }

    public function threePeaksAllDebitsInSubmission(ThreePeaksAllDebitsInSubmissionRequest $request, DebitBatch $debitBatch)
    {
        if (! $debitBatch->subid) {
            abort(400, 'This debit batch does not have a submission id');
        }

        try {
            return response($this->threePeaksService->getDebitsInSubmission(locationId: $debitBatch->location_id, submissionId: $debitBatch->subid));
        } catch (Exception $e) {
            abort(400, $e->getMessage());
        }
    }

    public function threePeaksValidateInformation(ThreePeaksValidateInformationRequest $request, DebitBatch $debitBatch)
    {
        if (! $debitBatch->subid) {
            abort(400, 'This debit batch does not have a submission id');
        }

        try {
            return response($this->threePeaksService->getValidationInformation(locationId: $debitBatch->location_id, submissionId: $debitBatch->subid));
        } catch (Exception $e) {
            abort(400, $e->getMessage());
        }
    }

    public function threePeaksRecallSubmission(ThreePeaksRecallSubmissionRequest $request, DebitBatch $debitBatch)
    {
        try {
            return response($this->threePeaksService->recallSubmission(locationId: $debitBatch->location_id, debitBatch: $debitBatch, message: 'Recall required by customer'));
        } catch (Exception $e) {
            abort(400, $e->getMessage());
        }
    }

    public function reconcileThreePeaksBatch(ReconcileThreePeaksBatchRequest $request, DebitBatch $debitBatch)
    {
        ReconcileBatch::dispatch($debitBatch)->onQueue('finance');

        return response()->noContent();
    }

    public function regenerateDebitBatchInvoices(RegenerateDebitBatchInvoicesRequest $request, DebitBatch $debitBatch)
    {
        if ($debitBatch->isProcessed()) {
            abort(400, 'This debit batch has already been processed.');
        }

        $userBatches = UserBatch::query()
            ->where('debit_batch_id', '=', $debitBatch->getKey())
            ->where('is_active', '=', true)
            ->get();

        foreach ($userBatches as $userBatch) {
            $invoice = $userBatch->invoice;

            if (in_array($invoice->status, ['submitted', 'paid'])) {
                continue;
            }

            $this->debitBatchService->markAsDeleted($invoice);

            $newInvoice = $this->financeService->generateInvoiceForUser(user: $userBatch->user, debitBatch: $userBatch->debitBatch);

            if (! $newInvoice) {
                continue;
            }

            $userBatch->update([
                'invoice_id' => $newInvoice->getKey(),
                'amount' => $newInvoice->amount,
            ]);
        }

        $this->debitBatchService->updateBatchTotal($debitBatch);

        return response()->noContent();
    }

    public function ensureUserOnBatch(EnsureUserOnBatchRequest $request, DebitBatch $debitBatch)
    {
        if ($debitBatch->isProcessed()) {
            abort(400, 'This debit batch has already been processed.');
        }

        $debitOrderTenantUsers = TenantUser::query()
            ->distinct()
            ->select('user_to_box.*')
            ->join('user_banking_details', function ($query) {
                $query->on('user_to_box.user_id', '=', 'user_banking_details.user_id');
                $query->on('user_to_box.box_id', '=', 'user_banking_details.box_id');
            })
            ->where('user_to_box.box_id', '=', $debitBatch->location->box_id)
            ->where('user_to_box.user_type_id', '=', UserType::GYM_MEMBER)
            ->where('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED)
            ->where('user_to_box.user_debit_status_id', '=', UserDebitStatus::DEBIT_ORDER)
            ->where('user_to_box.end_date', '>', today()->toDateString())
            ->where(function ($builder) use ($debitBatch) {
                $builder->where('user_banking_details.debit_day_id', $debitBatch->debitDayDate->debit_day_id)
                    ->where('user_banking_details.is_active', true);
            })
            ->whereIn('user_to_box.user_id', function ($builder) use ($debitBatch) {
                $builder->distinct()
                    ->select('user_to_facility.user_id')
                    ->from((new LocationUser())->getTable())
                    ->where('user_to_facility.box_facility_id', '=', $debitBatch->box_facility_id)
                    ->where('user_to_facility.end_date', '>', today()->toDateString());
            })->get();

        foreach ($debitOrderTenantUsers as $tenantUser) {
            if (! $this->debitBatchService->shouldTenantUserBeAddedToDebitBatch($tenantUser, $debitBatch)) {
                continue;
            }

            $invoice = $this->financeService->generateInvoiceForUser($tenantUser->user, $debitBatch);

            if (! $invoice instanceof UserInvoice) {
                continue;
            }

            $this->debitBatchService->createUserBatch($tenantUser->user, $invoice, $debitBatch);
        }

        $this->debitBatchService->updateBatchTotal($debitBatch);

        return response()->noContent();
    }

    #[QueryParam(name: 'date', type: 'string', description: 'If no date is supplied then the original debit batch date will be used', required: false)]
    #[QueryParam(name: 'is_process_as_same_day_batch', type: 'boolean', description: 'This is ONLY for Sage/Netcash debit batches. If false batch will be processed as 2 day batch.', required: true)]
    public function resubmit(ResubmitDebitBatchRequest $request, DebitBatch $debitBatch)
    {
        if (! $debitBatch->location->can_debit) {
            abort(Response::HTTP_UNAUTHORIZED, "'Can process debits' setting is not enabled for this location.");
        }

        $result = $this->debitBatchService->resubmit($debitBatch);

        if ($result['isSuccessful'] !== true) {
            abort(400, is_array($result['errors']) ? implode(',', $result['errors']) : $result['errors']);
        }

        return response()->noContent(200);
    }

    public function markAsUnprocessed(MarkDebitBatchAsUnprocessedRequest $request, DebitBatch $debitBatch)
    {
        if (! $debitBatch->isProcessed()) {
            abort(400, 'This debit batch has already been processed.');
        }

        $debitBatch->update([
            'is_processed' => false,
            'dt_processed' => null,
        ]);

        $loggedInUser = auth()->user();
        $nowStr = now()->format('Y-m-d H:i');

        // Log the activity
        $debitBatch->startLogEntry();
        $debitBatch->log("Batch was marked as un-processed by $loggedInUser->full_name ($loggedInUser->email) at $nowStr");
        $debitBatch->endLogEntry();

        $debitBatch->save();

        return response()->noContent();
    }

    #[QueryParam(name: 'date', type: 'string', required: true)]
    public function requestNetcashStatement(RequestNetcashStatementRequest $request, DebitBatch $debitBatch)
    {
        if ($debitBatch->location->payment_gateway_id !== PaymentGateway::SAGE_PAY_V3->value) {
            abort(400, 'This location does not use Netcash to process their debit batches.');
        }

        $date = Carbon::parse($request->get('date'));

        $result = (new NetcashService())->requestNetcashStatement($debitBatch, $date);

        if (is_string($result)) {
            abort(400, $result);
        } else {
            abort(400, 'Debit batch statement successfully requested for debit batch: '.$debitBatch->getKey().' - '.$date->format('Y-m-d'));
        }
    }

    #[QueryParam(name: 'bank_statement', type: 'file', description: 'This should be a camt.052 (Cash Management: Bank to Customer Account Report. Supported versions: camt.052001.: 01, 02, 04, 06 and 08) or camt.053 (Cash Management: bank to customer statement. Supported versions: camt.053001.: 02, 03, 04 and 08) file.', required: true)]
    public function reconcileSepaBankStatement(ReconcileSepaBankStatementRequest $request)
    {
        return response($this->debitBatchService->reconcileSepaDebitBatch($request->file('statement'), $request->tenant_id));
    }
}
