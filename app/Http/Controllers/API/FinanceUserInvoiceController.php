<?php

namespace App\Http\Controllers\API;

use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Exports\UserInvoiceExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\AddInvoiceToDebitBatchRequest;
use App\Http\Requests\Invoice\BulkCreateInvoicesRequest;
use App\Http\Requests\Invoice\BulkReverseInvoicesRequest;
use App\Http\Requests\Invoice\BulkSendInvoicesByUserRequest;
use App\Http\Requests\Invoice\BulkSendInvoicesRequest;
use App\Http\Requests\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Invoice\DeleteInvoiceRequest;
use App\Http\Requests\Invoice\DownloadInvoiceRequest;
use App\Http\Requests\Invoice\ExportInvoiceRequest;
use App\Http\Requests\Invoice\GenerateInvoicesRequest;
use App\Http\Requests\Invoice\ListInvoicesRequest;
use App\Http\Requests\Invoice\ListOwnInvoicesRequest;
use App\Http\Requests\Invoice\ReadInvoiceRequest;
use App\Http\Requests\Invoice\ReleaseInvoiceTopUpRequest;
use App\Http\Requests\Invoice\ReverseInvoiceRequest;
use App\Http\Requests\Invoice\SendInvoiceRequest;
use App\Http\Requests\Invoice\SendPaymentNoticesRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\UserInvoiceResource;
use App\Models\FinanceTopUp;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\LocationUser;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserBatch;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Models\UserInvoicePayment;
use App\Services\DebitBatchService;
use App\Services\FinanceService;
use App\Services\InvoiceService;
use App\Services\PaymentGateways\GoCardlessService;
use App\Services\PaymentGateways\PaymentGatewayService;
use App\Services\TenantUserService;
use App\Services\UserPackageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Maatwebsite\Excel\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class FinanceUserInvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoice, protected PaymentGatewayService $payments)
    {
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    #[QueryParam('filter[type]', 'string', required: true)]
    #[QueryParam('filter[payment_type]', 'string', required: false)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[package_id]', 'integer', required: false)]
    #[QueryParam('filter[between]', 'string', required: false)]
    #[QueryParam('filter[sent_status]', 'string', required: false)]
    #[QueryParam('filter[status]', 'string', required: false)]
    #[QueryParam('filter[search]', 'string', required: false)]
    public function list(ListInvoicesRequest $request)
    {
        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $request->input('filter.tenant_id'));

        return UserInvoiceResource::collection($this->invoice->getInvoices($request, $tenantUser));
    }

    public function store(CreateInvoiceRequest $request): JsonResponse|UserInvoiceResource
    {
        $errors = [];

        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($request->input('user_id'), $request->input('tenant_id'));
        $dueOn = $request->get('due_on');
        $status = $request->get('status');
        $lineItems = $request->get('line_items');

        if (! $userBoxMembership instanceof TenantUser) {
            $errors['tenant_user'] = 'Tenant User not found.';
        }

        if (! $dueOn) {
            $errors['due_on'] = 'Due on date is required.';
        }

        if (! $status) {
            $errors['status'] = 'Status is required.';
        }

        if (! $lineItems) {
            $errors['line_items'] = 'Line items are required.';
        }

        if (count($errors) > 0) {
            return response()->json($errors, 400);
        }

        $userFacilityMembership = (new TenantUserService())->getLocationUserByTenant($userBoxMembership->user, $userBoxMembership->tenant);

        if (! $userFacilityMembership instanceof LocationUser) {
            abort(400, 'This user does not have an active location membership.');
        }

        $dueOn = new \DateTime($dueOn);
        $boxFacility = $userFacilityMembership->location;

        $invoice = new UserInvoice();

        $invoice->setAttribute('description', $request->get('description'));
        $invoice->setAttribute('due_on', $dueOn);
        $invoice->setAttribute('period_start', $dueOn);
        $invoice->setAttribute('period_end', $dueOn);
        $invoice->setAttribute('code', (new FinanceService())->generateInvoiceNumberForFacility($boxFacility));
        $invoice->setAttribute('type', InvoiceType::INVOICE->value);
        $invoice->setAttribute('currency', $userBoxMembership->tenant->memberCurrency->code);
        $invoice->setAttribute('user_to_facility_id', $userFacilityMembership?->getKey());
        $invoice->setAttribute('status', $status);
        $invoice->save();
        $invoiceTotal = 0;

        // Create invoice items
        foreach ($lineItems as $invoiceItem) {
            $invoiceItemsErrors = [];

            $newInvoiceItem = new UserInvoiceItem();

            if (! isset($invoiceItem['discriminator']) || $invoiceItem['discriminator'] == '') {
                $invoiceItemsErrors['discriminator'] = 'Line item discriminator is required';
            }

            if (! isset($invoiceItem['description']) || $invoiceItem['description'] == '') {
                $invoiceItemsErrors['description'] = 'Line item description is required';
            }

            if (! isset($invoiceItem['unit_price']) || $invoiceItem['unit_price'] == '') {
                $invoiceItemsErrors['unit_price'] = 'Line item unit price is required';
            }

            $normalizedPrice = str_replace(',', '.', $invoiceItem['unit_price']);

            if (! is_numeric($normalizedPrice)) {
                $invoiceItemsErrors['unit_price'] = 'Line item unit price has to be a number';
            }

            if (! isset($invoiceItem['quantity']) || $invoiceItem['quantity'] == '') {
                $invoiceItemsErrors['quantity'] = 'Line item quantity is required';
            } elseif (! is_numeric($invoiceItem['quantity'])) {
                $invoiceItemsErrors['quantity'] = 'Line item quantity has to be a number';
            }

            if (count($invoiceItemsErrors) > 0) {
                return response()->json($invoiceItemsErrors, 400);
            }

            $itemTotal = $normalizedPrice * $invoiceItem['quantity'];

            if (isset($invoiceItem['code']) && $invoiceItem['code'] != '') {
                $newInvoiceItem->setAttribute('code', $invoiceItem['code']);
            }

            $newInvoiceItem->setAttribute('description', $invoiceItem['description']);
            $newInvoiceItem->setAttribute('unitPrice', $normalizedPrice);
            $newInvoiceItem->setAttribute('quantity', $invoiceItem['quantity']);
            $newInvoiceItem->setAttribute('discriminator', $invoiceItem['discriminator']);
            $newInvoiceItem->setAttribute('amount', $itemTotal);
            $newInvoiceItem->setAttribute('invoice_id', $invoice->getRouteKey());
            $newInvoiceItem->save();
            $invoiceTotal += $itemTotal;
        }

        $invoice->setAttribute('amount', $invoiceTotal);

        if ($invoice->status == InvoiceStatus::PAID) {
            $newPayment = new UserInvoicePayment();
            $newPayment->setAttribute('invoice_id', $invoice->getKey());
            $newPayment->setAttribute('date_time', new \DateTime());
            $newPayment->setAttribute('amount', $invoiceTotal);
            $newPayment->setAttribute('type', $request->get('payment_type', 'cash'));
            $newPayment->setAttribute('currency', $userBoxMembership->tenant->memberCurrency->code);
            $newPayment->setAttribute('user_to_facility_id', $invoice->locationUser?->getKey());
            $newPayment->setAttribute('reference', $request->get('payment_reference'));
            $newPayment->save();
        }

        $invoice->push();

        if ($request->get('is_send')) {
            (new InvoiceService())->sendInvoice($invoice);
        }

        return new UserInvoiceResource($invoice->loadMissing('userTenant', 'tenant'));
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[between]', 'string', required: false)]
    #[QueryParam('filter[status]', 'string', required: false)]
    #[QueryParam('sort[due_on]', 'string', required: false)]
    public function getInvoicesForUser(User $user, ListOwnInvoicesRequest $request)
    {
        $invoices = QueryBuilder::for(UserInvoice::class)
            ->from('finance_invoices', 'i')
            ->join('user_to_facility as fm', 'fm.user_to_facility_id', '=', 'i.user_to_facility_id')
            ->join('box_facility as f', 'fm.box_facility_id', '=', 'f.box_facility_id')
            ->where('fm.user_id', '=', $user->getKey())
            ->where('f.is_active', '=', true)
            ->where('i.deleted', '=', false)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'f.box_id'),
                AllowedFilter::exact('status', 'i.status'),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $query->whereBetween('i.due_on', $value);
                }),
            ])
            ->allowedIncludes([
                AllowedInclude::relationship('tenant.member_currency', 'tenant.memberCurrency'),
            ])
            ->withoutGlobalScopes()
            ->_paginate();

        return UserInvoiceResource::collection($invoices);
    }

    public function show(UserInvoice $userInvoice, ReadInvoiceRequest $request): UserInvoiceResource
    {
        return new UserInvoiceResource($userInvoice->loadMissing(['invoiceItems', 'payments']));
    }

    public function update(UserInvoice $userInvoice, UpdateInvoiceRequest $request): JsonResponse|UserInvoiceResource
    {
        $invoice = $userInvoice;

        $errors = [];

        $dueOn = $request->input('due_on');
        $lineItems = $request->input('line_items');
        $creditAmount = $request->input('credit_amount');

        if (! $dueOn) {
            $errors['due_on'] = 'Due on date is required.';
        }

        if ($invoice->type == InvoiceType::INVOICE) {
            if (! $lineItems) {
                $errors['line_times'] = 'Line items are required.';
            }
        } elseif ($invoice->type == InvoiceType::CREDIT_NOTE) {
            if (! $creditAmount) {
                $errors['credit_amount'] = 'Credit amount is required.';
            }
        }

        if (count($errors) > 0) {
            return response()->json($errors, 400);
        }

        $invoice
            ->setAttribute('description', $request->input('description'))
            ->setAttribute('due_on', Carbon::parse($dueOn))
            ->setAttribute('period_start', $dueOn)
            ->setAttribute('period_end', $dueOn);

        if ($invoice->type == InvoiceType::INVOICE) {
            $invoiceTotal = 0;

            // Delete all the existing line items
            $invoiceItems = (new InvoiceService())->getInvoiceItemsForInvoice($invoice);

            /** @var UserInvoiceItem $invoiceItem */
            foreach ($invoiceItems as $invoiceItem) {
                $invoiceItem->setAttribute('deleted', true);
                $invoiceItem->save();
            }

            // Create invoice items
            foreach ($lineItems as $invoiceItem) {
                $invoiceItemsErrors = [];

                if (! isset($invoiceItem['discriminator']) || $invoiceItem['discriminator'] == '') {
                    $invoiceItemsErrors['discriminator'] = 'Line item discriminator is required';
                }

                if (! isset($invoiceItem['description']) || $invoiceItem['description'] == '') {
                    $invoiceItemsErrors['description'] = 'Line item description is required';
                }

                if (! isset($invoiceItem['unit_price']) || $invoiceItem['unit_price'] == '') {
                    $invoiceItemsErrors['unit_price'] = 'Line item unit price is required';
                }
                $normalizedPrice = str_replace(',', '.', $invoiceItem['unit_price']);

                if (! is_numeric($normalizedPrice)) {
                    $invoiceItemsErrors['unit_price'] = 'Line item unit price has to be a number';
                }

                if (! isset($invoiceItem['quantity']) || $invoiceItem['quantity'] == '') {
                    $invoiceItemsErrors['quantity'] = 'Line item quantity is required';
                } elseif (! is_numeric($invoiceItem['quantity'])) {
                    $invoiceItemsErrors['quantity'] = 'Line item quantity has to be a number';
                }

                if (count($invoiceItemsErrors) > 0) {
                    return response()->json($invoiceItemsErrors, 400);
                }

                $newInvoiceItem = new UserInvoiceItem();

                if (isset($invoiceItem['code']) && $invoiceItem['code'] != '') {
                    $newInvoiceItem->setAttribute('code', $invoiceItem['code']);
                }

                $itemTotal = $normalizedPrice * $invoiceItem['quantity'];

                $newInvoiceItem
                    ->setAttribute('description', $invoiceItem['description'])
                    ->setAttribute('unitPrice', $normalizedPrice)
                    ->setAttribute('quantity', $invoiceItem['quantity'])
                    ->setAttribute('discriminator', $invoiceItem['discriminator'])
                    ->setAttribute('amount', $itemTotal)
                    ->setAttribute('invoice_id', $invoice->getKey());

                $newInvoiceItem->save();

                $invoiceTotal += $itemTotal;
            }

            // Set new amount
            $invoice->setAttribute('amount', $invoiceTotal);

            // Update debit bach amount if this invoice is for a debit batch
            if ($invoice->userBatch instanceof UserBatch) {
                $userBatch = $invoice->userBatch;

                $userBatch->update(['amount_editable' => $invoice->amount]);

                // Update the debit batch total
                (new DebitBatchService())->updateBatchTotal($userBatch->debitBatch);
            }
        } elseif ($invoice->type == InvoiceType::CREDIT_NOTE) {
            $invoice->setAttribute('amount', $creditAmount);
        }

        $invoice->update();

        return new UserInvoiceResource($userInvoice);
    }

    public function delete(DeleteInvoiceRequest $request, UserInvoice $userInvoice): Response|JsonResponse
    {
        $invoice = $userInvoice;

        if ($invoice->sent_on && $invoice->sent_on !== null) {
            abort(400, 'This invoice cannot be deleted because it has already been sent to the member.');
        }

        if ($invoice->gateway_payment_id && $invoice->status === InvoiceStatus::SUBMITTED) {
            $boxFacility = null;

            if ($invoice->locationUser instanceof LocationUser) {
                $boxFacility = $invoice->locationUser->location;
            } elseif ($invoice->location instanceof Location) {
                $boxFacility = $invoice->location;
            } elseif ($invoice->locationPaymentGateway instanceof LocationPaymentGateway) {
                $boxFacility = $invoice->locationPaymentGateway->location;
            }

            if ($boxFacility instanceof Location && $boxFacility->paymentGateway->getKey() === PaymentGateway::GO_CARDLESS) {
                $result = (new GoCardlessService())->cancelPaymentForInvoice($boxFacility, $invoice);

                if (is_string($result)) {
                    abort(400, 'Invoice could not be deleted because of a payment that was made for it.');
                }
            }
        }

        $response = (new InvoiceService())->markInvoiceAsDeleted($invoice);

        if (! $response instanceof UserInvoice) {
            return response()->json($response, 400);
        }

        $invoice->save();

        return response()->noContent();
    }

    public function send(SendInvoiceRequest $request, UserInvoice $userInvoice): Response
    {
        (new InvoiceService())->sendInvoice($userInvoice);

        return response()->noContent();
    }

    public function download(DownloadInvoiceRequest $request, UserInvoice $userInvoice): JsonResponse
    {
        $invoice = $userInvoice;

        $openingBalance = 0;

        if ($invoice->lead_member_id == null && $invoice->non_member_name == null && $invoice->non_member_email == null) {
            // Invoices before start date
            $beforeDate = clone $invoice->due_on;
            $beforeDate->sub(new \DateInterval('P1D'));
            $user = $invoice->locationUser->user_id ? $invoice->locationUser->user : $invoice->locationUser->leadMember->user;

            $invoicesBeforeDate = (new InvoiceService())->getInvoicesForUserBeforeDate($user, $invoice->locationUser->tenant, InvoiceType::INVOICE->value, $beforeDate);
            $creditNotesBeforeDate = (new InvoiceService())->getInvoicesForUserBeforeDate($user, $invoice->locationUser->tenant, InvoiceType::CREDIT_NOTE->value, $beforeDate);

            $openingBalance = (new InvoiceService())->getInvoiceBalance($invoicesBeforeDate, $creditNotesBeforeDate);
        }

        $filename = $invoice->invoice_member_name.'_'.$invoice->due_on->format('Y-m-d').'_invoice.pdf';

        // Upload the file
        (new InvoiceService())->generateUserInvoicePDF($invoice, $openingBalance, $filename);

        return response()->json(
            Storage::disk('tmp')->temporaryUrl('user-invoices/'.$filename, now()->addMinutes(10))
        );
    }

    public function export(ExportInvoiceRequest $request): JsonResponse
    {
        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $request->input('filter.tenant_id'));

        if (! $tenantUser) {
            abort(400, 'Tenant user not found.');
        }

        $location = Location::query()->find($request->input('filter.location_id')) ?? null;

        $filepath = str('user-invoices-exports/')
            ->append($location ? $location?->name : $location?->tenant->name)
            ->append('-invoices-')
            ->append(explode(',', $request->input('filter.between'))[0])
            ->append('-')
            ->append(explode(',', $request->input('filter.between'))[1])
            ->append('.xls')
            ->toString();

        (new UserInvoiceExport($tenantUser))->store($filepath, 'tmp', Excel::XLS);
        $url = Storage::disk('tmp')->temporaryUrl($filepath, now()->addMinutes(5));

        return response()->json($url);
    }

    public function reverse(ReverseInvoiceRequest $request, UserInvoice $userInvoice): UserInvoiceResource
    {
        $invoice = $userInvoice;

        if ($invoice->status === InvoiceStatus::CREDITED) {
            abort(400, 'The selected invoice has already been credited.');
        }

        return new UserInvoiceResource((new InvoiceService())->createCreditNoteForInvoice($invoice));
    }

    public function releaseTopUp(ReleaseInvoiceTopUpRequest $request, UserInvoice $userInvoice): Response
    {
        $invoice = $userInvoice;

        if ($invoice->discriminator !== InvoiceDiscriminator::TOPUP_INVOICE) {
            abort(400, 'This is not a top-up invoice.');
        }

        $topUp = FinanceTopUp::query()
            ->where('invoice_id', '=', $invoice->getKey())
            ->where('released', '=', false)
            ->where('deleted', '=', false)
            ->first();

        if (! $topUp instanceof FinanceTopUp) {
            abort(404, 'Top-up invoice could not be found.');
        }

        $totalSessions = $topUp->userPackage->sessions_available + $topUp->number_of_sessions;

        $topUp->userPackage->update([
            'sessions_available' => $totalSessions,
        ]);

        $topUp->update([
            'released' => true,
        ]);

        return response()->noContent();
    }

    public function sendPaymentRequests(SendPaymentNoticesRequest $request): JsonResponse
    {
        $invoiceIds = $request->get('invoice_ids');
        $locationPaymentGateway = LocationPaymentGateway::query()->find($request->get('location_payment_gateway_id'));
        $isImmediatePayment = $request->get('is_immediate_payment');

        if (! $locationPaymentGateway instanceof LocationPaymentGateway) {
            abort(400, 'Location payment gateway settings could not be found');
        }

        if (! count($invoiceIds) > 0) {
            abort(400, 'No invoice id\'s not found.');
        }

        $result = [
            'requested' => [],
            'processed' => [],
            'failed' => [],
        ];

        foreach ($invoiceIds as $invoiceId) {
            $invoice = UserInvoice::query()->find($invoiceId);

            if (! $invoice instanceof UserInvoice) {
                continue;
            }

            if ($invoice->status === InvoiceStatus::SUBMITTED) {
                $result['failed'][] = [
                    'invoice' => new UserInvoiceResource($invoice),
                    'message' => 'Invoice has already been submitted to be processed.',
                ];

                continue;
            }

            // Set $boxFacilityPaymentGatewayId on invoice
            $invoice->update(['facility_to_payment_gateway_id' => $locationPaymentGateway->getKey()]);

            // Attempt payment.
            $paymentAttemptResult = (new PaymentGatewayService())->attemptPayment($invoice, $locationPaymentGateway);

            if ($paymentAttemptResult && ! is_string($paymentAttemptResult)) {
                $result['processed'][] = [
                    'invoice' => new UserInvoiceResource($invoice),
                    'message' => null,
                ];
            } elseif (is_string($paymentAttemptResult)) {
                $result['failed'][] = [
                    'invoice' => new UserInvoiceResource($invoice),
                    'message' => $paymentAttemptResult,
                ];
            } elseif ($paymentAttemptResult === false) {
                // Send payment request
                if (count($invoiceIds) === 1 && $isImmediatePayment) {
                    $result['link'] = config('octiv.web_app_url').'/payment/'.$invoice->getKey().'?gid='.$locationPaymentGateway->getKey();
                } else {
                    // Attempt sending request
                    $paymentRequestResult = (new PaymentGatewayService())->createPaymentRequest($invoice, $locationPaymentGateway);

                    if ($paymentRequestResult === true) {
                        $result['requested'][] = [
                            'invoice' => new UserInvoiceResource($invoice),
                            'message' => null,
                        ];
                    } else {
                        $result['failed'][] = [
                            'invoice' => new UserInvoiceResource($invoice),
                            'message' => 'Payment request could not be created.',
                        ];
                    }
                }
            }
        }

        return response()->json($result, 201);
    }

    public function generate(GenerateInvoicesRequest $request): JsonResponse|UserInvoiceResource
    {
        $dueOn = Carbon::parse($request->get('due_on'));
        $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($request->get('user_id'), $request->get('tenant_id'));

        if (! $userBoxMembership instanceof TenantUser) {
            abort(400, 'Tenant User could not be found.');
        }

        $user = $userBoxMembership->user;

        // Check if user has active package(s)
        if ((new UserPackageService())->getActiveUserPackagesForTenant($user, $userBoxMembership->tenant, $dueOn)->count() === 0) {
            abort(400, 'User does not have an active package.');
        }

        $invoice = (new InvoiceService())->generateUserInvoice($user, $userBoxMembership->tenant, $dueOn);

        if ($invoice) {
            if ($request->get('is_send')) {
                (new InvoiceService())->sendInvoice($invoice);
            }

            return new UserInvoiceResource($invoice);
        }

        return response()->json(['Error generating invoice, please contact admin'], 400);

    }

    public function bulkCreate(BulkCreateInvoicesRequest $request): Response
    {
        $tenant = Tenant::query()->find($request->get('tenant_id'));
        $userIds = $request->get('user_ids');
        $dueOn = Carbon::parse($request->get('due_on'));

        foreach ($userIds as $userId) {
            $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenant);

            if (! $userTenant instanceof TenantUser) {
                continue;
            }

            if ($userTenant->tenant_id !== $tenant->tenant_id) {
                continue;
            }

            $user = $userTenant->user;

            // Check if user has active package(s)
            if ((new UserPackageService())->getActiveUserPackagesForTenant($user, $tenant, $dueOn)->count() === 0) {
                continue;
            }

            $isGenerateInvoice = true;

            if ($userTenant->user_debit_status_id instanceof UserDebitStatus && $userTenant->user_debit_status_id == UserDebitStatus::UP_FRONT_PAYMENT) {
                $latestUpfrontInvoice = (new InvoiceService())->getUserLatestUpfrontInvoice($user, $tenant);

                if ($latestUpfrontInvoice instanceof UserInvoice) {
                    $isGenerateInvoice = false;
                }
            }

            if ($isGenerateInvoice) {
                $invoice = (new InvoiceService())->generateUserInvoice($user, $tenant, Carbon::parse($request->get('due_on')));

                if ($request->get('is_send')) {
                    (new InvoiceService())->sendInvoice($invoice);
                }
            }
        }

        return response()->noContent(201);
    }

    public function bulkReverse(BulkReverseInvoicesRequest $request): Response|JsonResponse
    {
        // Temporary increase execution time for this action
        set_time_limit(300);

        $invoiceIds = $request->get('invoice_ids');

        $errors = [];

        if (! count($invoiceIds) > 0) {
            $errors['invoiceIds'] = 'Invoice id\'s not found.';
        }

        if (count($errors) > 0) {
            return response()->json($errors, 400);
        }

        foreach ($invoiceIds as $invoiceId) {
            $invoice = UserInvoice::query()->find($invoiceId);

            if (! $invoice instanceof UserInvoice) {
                continue;
            }

            if ($invoice->userBatch instanceof UserBatch && $invoice->userBatch->debitBatch->is_processed) {
                continue;
            }

            if ($invoice->sent_on && $invoice->sent_on !== null) {
                (new InvoiceService())->createCreditNoteForInvoice($invoice);
            } else {
                (new InvoiceService())->markInvoiceAsDeleted($invoice);
            }
        }

        return response()->noContent();
    }

    public function bulkSendByUser(BulkSendInvoicesByUserRequest $request): Response|JsonResponse
    {
        // Temporary increase execution time for this action
        set_time_limit(300);

        $userBoxMembershipIds = array_unique($request->get('user_ids'));

        $errors = [];

        if (! count($userBoxMembershipIds) > 0) {
            $errors['user_ids'] = 'User id\'s not found.';
        }

        if (count($errors) > 0) {
            return response()->json($errors, 400);
        }

        $monthStartDate = new \DateTime(date('Y-m-d', strtotime('first day of this month')));
        $monthEndDate = new \DateTime(date('Y-m-d', strtotime('last day of this month')));

        foreach ($userBoxMembershipIds as $userBoxMembershipId) {
            $userBoxMembership = TenantUser::query()
                ->where('box_id', '=', $request->get('tenant_id'))
                ->where('user_id', '=', $userBoxMembershipId)
                ->first();

            if (! $userBoxMembership instanceof TenantUser) {
                continue;
            }

            if ($userBoxMembership->box_id !== (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $request->get('tenant_id'))->box_id) {
                continue;
            }

            // Get user invoice for current month
            $invoice = (new InvoiceService())->getUsersLastMembershipInvoiceForTenant($userBoxMembership->user, $userBoxMembership->tenant, $monthStartDate, $monthEndDate);

            if (! $invoice instanceof UserInvoice) {
                continue;
            }

            (new InvoiceService())->sendInvoice($invoice);
        }

        return response()->noContent();
    }

    public function bulkSendByInvoice(BulkSendInvoicesRequest $request): Response|JsonResponse
    {
        // Temporary increase execution time for this action
        set_time_limit(300);

        $invoiceIds = $request->get('invoice_ids');

        $errors = [];

        if (! count($invoiceIds) > 0) {
            $errors['invoice_ids'] = 'Invoice id\'s not found.';
        }

        if (count($errors) > 0) {
            return response()->json($errors, 400);
        }

        foreach ($invoiceIds as $invoiceId) {
            $invoice = UserInvoice::query()->find($invoiceId);

            if (! $invoice instanceof UserInvoice) {
                continue;
            }

            (new InvoiceService())->sendInvoice($invoice);
        }

        return response()->noContent();
    }

    public function addToDebitBatch(AddInvoiceToDebitBatchRequest $request, UserInvoice $userInvoice)
    {
        (new DebitBatchService())->addInvoiceToDebitBatch($userInvoice, $request->get('debit_batch_id'));

        return response()->noContent();
    }
}
