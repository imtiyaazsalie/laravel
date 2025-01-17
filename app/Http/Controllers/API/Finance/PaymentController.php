<?php

namespace App\Http\Controllers\API\Finance;

use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\PackageType;
use App\Enums\PaymentGatewayContext;
use App\Exports\PaymentExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\Payment\BulkCreatePaymentRequest;
use App\Http\Requests\Finance\Payment\CreatePaymentRequest;
use App\Http\Requests\Finance\Payment\DeletePaymentRequest;
use App\Http\Requests\Finance\Payment\ExportPaymentsRequest;
use App\Http\Requests\Finance\Payment\ListPaymentsRequest;
use App\Http\Requests\Finance\Payment\UpdatePaymentRequest;
use App\Http\Resources\UserInvoicePaymentResource;
use App\Http\Resources\UserInvoiceResource;
use App\Models\FinanceTopUp;
use App\Models\LocationPaymentGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBatch;
use App\Models\UserInvoice;
use App\Models\UserInvoicePayment;
use App\Services\InvoiceService;
use App\Services\NonceService;
use App\Services\PaymentGateways\PaymentGatewayService;
use App\Services\PaymentGateways\PaymentService;
use App\Services\TagsService;
use App\Services\WidgetService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Maatwebsite\Excel\Facades\Excel;

class PaymentController extends Controller
{
    public function __construct(protected TagsService $tags)
    {
    }

    #[QueryParam('filter[invoice_member_type]', 'string', required: true)]
    #[QueryParam('filter[user_id]', 'integer', required: false)]
    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[type]', 'string', required: false)]
    #[QueryParam('filter[between]', 'string', required: true)]
    public function list(ListPaymentsRequest $request)
    {
        $invoiceMemberType = $request->input('filter.invoice_member_type');
        $user = $request->input('filter.user_id') ? User::query()->find($request->input('filter.user_id')) : null;

        if ($invoiceMemberType === 'leadMemberInvoicePayments') {
            $paymentsQueryBuilder = (new PaymentService())->getLeadPaymentsQueryBuilder();
        } elseif ($invoiceMemberType === 'athleteInvoicePayments') {
            $paymentsQueryBuilder = (new PaymentService())->getPaymentsQueryBuilder();

            if ($user instanceof User) {
                $paymentsQueryBuilder->where('user_to_facility.user_id', $user->getKey());
            }
        } else {
            abort(404, 'Not found.');
        }

        return UserInvoicePaymentResource::collection($paymentsQueryBuilder->_paginate());
    }

    #[QueryParam('filter[invoice_member_type]', 'string', required: true)]
    #[QueryParam('filter[user_id]', 'integer', required: false)]
    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[type]', 'string', required: false)]
    #[QueryParam('filter[between]', 'string', required: true)]
    public function export(ExportPaymentsRequest $request)
    {
        $invoiceMemberType = $request->input('filter.invoice_member_type');
        $user = $request->input('filter.user_id') ? User::query()->find($request->input('filter.user_id')) : null;
        $tenant = Tenant::query()->find($request->input('filter.tenant_id'));

        if ($invoiceMemberType === 'leadMemberInvoicePayments') {
            $paymentsQueryBuilder = (new PaymentService())->getLeadPaymentsQueryBuilder();
        } elseif ($invoiceMemberType === 'athleteInvoicePayments') {
            $paymentsQueryBuilder = (new PaymentService())->getPaymentsQueryBuilder();

            if ($user instanceof User) {
                $paymentsQueryBuilder->where('user_to_facility.user_id', $user->getKey());
            }
        } else {
            abort(404, 'Not found.');
        }

        $data = [];

        /** @var UserInvoicePayment $payment */
        foreach ($paymentsQueryBuilder->get() as $payment) {
            if ($invoiceMemberType == 'leadMemberInvoicePayments') {
                $member = $payment->invoice->leadMember->name;
            } elseif ($invoiceMemberType === 'athleteInvoicePayments') {
                $member = $payment->invoice->invoiceMemberName ?? $payment->getKey();
            } else {
                continue;
            }

            $data[] = [
                'Invoice #' => $payment->invoice->code,
                'Member' => $member,
                'Date' => Carbon::parse($payment->date_time)->format('Y-m-d'),
                'Type' => $payment->type === InvoicePaymentType::DEBIT_ORDER ? 'Debit order' : ucfirst($payment->type->value),
                'Amount' => number_format($payment->amount, 2, '.', ''),
            ];
        }

        $export = new PaymentExport($data);
        $filename = $tenant->box_desc.'-Payment-'.now()->format('Y-m-d H:i:s').'.csv';
        Excel::store($export, $filename, 'tmp', \Maatwebsite\Excel\Excel::CSV);

        return response()->json(Storage::disk('tmp')->temporaryUrl($filename, now()->addMinute()));
    }

    public function getInvoiceForPayment(Request $request, UserInvoice $invoice)
    {
        if ($invoice->isUnpaid()) {
            $invoice->setAttribute('nonce', (new NonceService)->generateNonce($invoice->getKey()));

            if (filter_var($request->input('include_payment_gateways'), FILTER_VALIDATE_BOOLEAN)) {
                $location = $invoice->invoice_location;

                if (! $location) {
                    abort(404, 'Invoice does not have a location');
                }

                $locationPaymentGateways = (new PaymentGatewayService())->getAppropriateLocationPaymentGateways(
                    $location->tenant, $location, PaymentGatewayContext::AD_HOC, true
                );

                $paymentGateways = [];

                /** @var LocationPaymentGateway $locationPaymentGateway */
                foreach ($locationPaymentGateways as $locationPaymentGateway) {
                    if ($locationPaymentGateway->settings()->exists()) {
                        $adhocSettings[$locationPaymentGateway->paymentGateway->getKey()] = [
                            'paymentGatewayId' => $locationPaymentGateway->paymentGateway->payment_gateway_id,
                            'paymentGatewaySettingsId' => $locationPaymentGateway->settings->setting_id,
                        ];

                        if ($locationPaymentGateway->settings->discr == 'sage') {
                            $adhocSettings[$locationPaymentGateway->paymentGateway->getKey()]['payNowServiceKey'] = $locationPaymentGateway->settings->pay_now_service_key;
                            $adhocSettings[$locationPaymentGateway->paymentGateway->getKey()]['softwareVendorKey'] = config('netcash.api.software_vendor_key');
                        } elseif ($locationPaymentGateway->settings->discr == 'stripe') {
                            $adhocSettings[$locationPaymentGateway->paymentGateway->getKey()]['publicKey'] = $locationPaymentGateway->settings->public_key;
                        }

                        $paymentGateways = $adhocSettings;
                    }
                }

                $invoice->setAttribute('payment_gateway_settings', $paymentGateways);
            }
        } elseif ($invoice->isPaid() && $invoice->discriminator === InvoiceDiscriminator::DROP_IN_INVOICE && $invoice->locationUser?->leadMember) {
            $invoice->setAttribute('drop_in', [
                'public_token' => $invoice->invoice_location->tenant->public_token,
                'lead_token' => $invoice->locationUser->leadMember->getKey(),
            ]);
        }

        return new UserInvoiceResource($invoice->loadMissing('tenant.memberCurrency', 'location', 'invoiceItems'));
    }

    public function getLocationPaymentGateway(LocationPaymentGateway $locationPaymentGateway)
    {
        if ($locationPaymentGateway->settings()->exists()) {
            $adhocSettings = [
                'paymentGatewayId' => $locationPaymentGateway->paymentGateway->payment_gateway_id,
                'paymentGatewaySettingsId' => $locationPaymentGateway->settings->setting_id,
            ];

            if ($locationPaymentGateway->settings->discr == 'sage') {
                $adhocSettings['payNowServiceKey'] = $locationPaymentGateway->settings->pay_now_service_key;
            } elseif ($locationPaymentGateway->settings->discr == 'stripe') {
                $adhocSettings['publicKey'] = $locationPaymentGateway->settings->public_key;
            }

            return $adhocSettings;
        }

        return null;
    }

    public function store(UserInvoice $invoice, CreatePaymentRequest $request): UserInvoicePaymentResource
    {
        $date = $request->get('date');
        $type = $request->get('type');
        $amount = $request->get('amount');
        $reference = $request->get('reference');

        $normalizedAmount = str_replace(',', '.', $amount);

        $payment = (new InvoiceService())->createPaymentForInvoice($invoice, $type, $normalizedAmount, Carbon::parse($date), $reference, $request->get('tag_id'));

        // For debit order invoices: Reason is so that members don't get billed twice if they made a payment before the batch was submitted
        $userBatch = $invoice->userBatch;

        if ($userBatch instanceof UserBatch && ! $userBatch->debitBatch->is_processed) {
            if ($invoice->isPaid()) {
                // Remove userBatch if invoice is fully paid
                $userBatch->setAttribute('is_active', false);
            } else {
                // Update userBatch if it was a partial payment
                $userBatch->setAttribute('amount', $invoice->outstanding_amount);
            }

            $userBatch->save();
        }

        return new UserInvoicePaymentResource($payment->refresh());
    }

    public function update(UserInvoicePayment $payment, UpdatePaymentRequest $request): UserInvoicePaymentResource
    {
        $amount = $request->get('amount');
        $normalizedAmount = str_replace(',', '.', $amount);
        $invoice = $payment->invoice;

        $payment->update([
            'date_time' => Carbon::parse($request->get('date')),
            'type' => $request->get('type'),
            'amount' => $normalizedAmount,
            'reference' => $request->get('reference'),
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

        // For debit order invoices: Reason is so that members don't get billed twice if they made a payment before the batch was submitted
        $userBatch = $invoice->userBatch;

        if ($userBatch instanceof UserBatch && ! $userBatch->debitBatch->is_processed) {
            if ($invoice->isPaid()) {
                // Remove userBatch if invoice is fully paid
                $userBatch->setAttribute('is_active', false);
            } else {
                // Update userBatch if it was a partial payment
                $userBatch->setAttribute('amount', $invoice->outstanding_amount);
                $userBatch->setAttribute('is_active', true);
            }

            $userBatch->save();
        }

        if ($request->has('tag_id')) {
            $this->tags->sync($request->tag_id, $payment, auth()->user()->getAuthIdentifier());
        }

        return new UserInvoicePaymentResource($payment);
    }

    public function bulkCreate(BulkCreatePaymentRequest $request): Response
    {
        foreach ($request->get('invoice_ids') as $invoiceId) {
            $invoice = UserInvoice::query()->find($invoiceId);

            if (! $invoice instanceof UserInvoice) {
                continue;
            }

            if ($invoice->isPaid()) {
                continue;
            }

            (new InvoiceService())->createPaymentForInvoice(
                $invoice,
                $request->get('type'),
                $invoice->amount,
                Carbon::parse($request->get('date')),
                $request->get('reference'),
                [$request->get('tag_id')]
            );

            // For debit order invoices: Reason is so that members don't get billed twice if they made a payment before the batch was submitted
            $userBatch = $invoice->userBatch;

            if ($userBatch instanceof UserBatch && ! $userBatch->debitBatch->is_processed) {

                if ($invoice->status === InvoiceStatus::PAID) {
                    // Remove userBatch if invoice is fully paid
                    $userBatch->setAttribute('is_active', false);
                } else {
                    // Update userBatch if it was a partial payment
                    $userBatch->setAttribute('amount', $invoice->outstanding_amount);
                }

                $userBatch->save();
            }
        }

        return response()->noContent();
    }

    public function delete(DeletePaymentRequest $request, UserInvoicePayment $payment): Response
    {
        $payment->setAttribute('deleted', true);

        $invoice = $payment->invoice;

        $payment->save();

        if ($invoice->payments->count() > 0 && $invoice->outstanding_amount <= 0) {
            $invoice->setAttribute('status', InvoiceStatus::PAID->value);
        } else {
            $invoice->setAttribute('status', InvoiceStatus::UNPAID->value);
        }

        $invoice->save();

        // For debit order invoices: Reason is so that members don't get billed twice if they made a payment before the batch was submitted
        $userBatch = $invoice->userBatch;

        if ($invoice->status === InvoiceStatus::UNPAID && $userBatch instanceof UserBatch && ! $userBatch->debitBatch->is_processed) {
            // Reactivate userBatch if invoice is unpaid and update userBatch outstandingAmount
            $userBatch
                ->setAttribute('amount_editable', $invoice->outstanding_amount)
                ->setAttribute('is_active', true);
            $userBatch->save();
        }

        return response()->noContent();
    }
}
