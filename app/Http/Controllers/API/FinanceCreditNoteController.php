<?php

namespace App\Http\Controllers\API;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Exports\CreditNoteExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreditNote\CreateCreditNoteRequest;
use App\Http\Requests\CreditNote\ExportCreditNotesRequest;
use App\Http\Requests\CreditNote\ListCreditNotesRequest;
use App\Http\Requests\CreditNote\ReadCreditNoteRequest;
use App\Http\Requests\CreditNote\UpdateCreditNoteRequest;
use App\Http\Resources\UserInvoiceResource;
use App\Models\LocationUser;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvoice;
use App\Models\UserPackage;
use App\Services\FinanceService;
use App\Services\TenantUserService;
use App\Services\UserPackageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class FinanceCreditNoteController extends Controller
{
    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[between]', 'string', required: true)]
    #[QueryParam('filter[sent_status]', 'string', required: false)]
    #[QueryParam('filter[search]', 'string', required: false)]
    public function list(ListCreditNotesRequest $request)
    {
        $invoices = QueryBuilder::for(UserInvoice::class)
            ->select('finance_invoices.*')
            ->join('user_to_facility', 'user_to_facility.user_to_facility_id', '=', 'finance_invoices.user_to_facility_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'user_to_facility.box_facility_id')
            ->where('finance_invoices.deleted', '=', false)
            ->whereNull('finance_invoices.lead_member_id')
            ->where('finance_invoices.type', '=', InvoiceType::CREDIT_NOTE->value)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_facility.box_id'),
                AllowedFilter::exact('location_id', 'box_facility.box_facility_id'),
                AllowedFilter::exact('type', 'finance_invoice.type'),
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

                    $query->join('users', 'user_to_facility.user_id', '=', 'users.user_id')
                        ->whereRaw("(users.name LIKE '%$value%' OR users.surname LIKE '%$value%' OR CONCAT(CONCAT(users.name, ' '),  users.surname) LIKE '%$value%' OR users.email LIKE '%$value%' OR finance_invoices.description LIKE '%$value%' OR finance_invoices.code LIKE '%$value%')");
                }),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $start = Carbon::parse($value[0])->setTime(0, 0)->format('Y-m-d H:i');
                    $end = Carbon::parse($value[1])->setTime(23, 59, 59)->format('Y-m-d H:i');
                    $query->whereBetween('finance_invoices.due_on', [$start, $end]);
                }),
            ])
            ->allowedSorts([
                AllowedSort::field('due_on', 'finance_invoices.due_on'),
            ])
            ->distinct()
            ->_paginate();

        return UserInvoiceResource::collection($invoices);
    }

    public function show(ReadCreditNoteRequest $request, UserInvoice $userInvoice): UserInvoiceResource
    {
        return new UserInvoiceResource($userInvoice);
    }

    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[between]', 'string', required: true)]
    #[QueryParam('filter[sent_status]', 'string', required: false)]
    #[QueryParam('filter[search]', 'string', required: false)]
    public function export(ExportCreditNotesRequest $request)
    {
        $data = [];

        $creditNotes = QueryBuilder::for(UserInvoice::class)
            ->join('user_to_facility', 'user_to_facility.user_to_facility_id', '=', 'finance_invoices.user_to_facility_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'user_to_facility.box_facility_id')
            ->where('finance_invoices.deleted', '=', false)
            ->whereNull('finance_invoices.lead_member_id')
            ->where('finance_invoices.type', '=', InvoiceType::CREDIT_NOTE->value)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_facility.box_id'),
                AllowedFilter::exact('location_id', 'box_facility.box_facility_id'),
                AllowedFilter::exact('type', 'finance_invoice.type'),
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

                    $query->join('users', 'user_to_facility.user_id', '=', 'users.user_id')
                        ->whereRaw("(users.name LIKE '%$value%' OR users.surname LIKE '%$value%' OR CONCAT(CONCAT(users.name, ' '),  users.surname) LIKE '%$value%' OR users.email LIKE '%$value%' OR finance_invoices.description LIKE '%$value%' OR finance_invoices.code LIKE '%$value%')");
                }),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $start = Carbon::parse($value[0])->setTime(0, 0)->format('Y-m-d H:i');
                    $end = Carbon::parse($value[1])->setTime(23, 59, 59)->format('Y-m-d H:i');
                    $query->whereBetween('finance_invoices.due_on', [$start, $end]);
                }),
            ])
            ->allowedSorts([
                AllowedSort::field('due_on', 'finance_invoices.due_on'),
            ])
            ->distinct()
            ->get();

        /** @var UserInvoice $creditNote */
        foreach ($creditNotes as $creditNote) {
            $data[] = [
                'id' => $creditNote->getKey(),
                'code' => $creditNote->code,
                'Member' => $creditNote->invoice_member_name,
                'description' => $creditNote->description,
                'status' => $creditNote->status?->name,
                'Due on' => $creditNote->due_on->toDateString(),
                'Sent on' => $creditNote->sent_on?->toDateString(),
                'Amount' => number_format($creditNote->amount, 2, '.', ''),
            ];
        }

        $startDate = explode(',', $request->input('filter.between'))[0];
        $endDate = explode(',', $request->input('filter.between'))[1];
        $filename = "Credit-notes-exports/credit-notes-$startDate-$endDate.csv";

        Excel::store(new CreditNoteExport($data), $filename, 'tmp');

        return response()->json(Storage::disk('tmp')->temporaryUrl($filename, now()->addMinute()));
    }

    public function store(CreateCreditNoteRequest $request): UserInvoiceResource
    {
        $tenant = Tenant::find($request->input('tenant_id'));
        $user = User::find($request->input('user_id'));
        $userLocation = (new TenantUserService())->getLocationUserByTenant($user, $tenant);
        $userPackage = (new UserPackageService())->getActiveUserPackageForTenant($user, $tenant);

        if (! $userLocation instanceof LocationUser) {
            abort(400, 'This user does not have an active location membership.');
        }

        if (! $userPackage instanceof UserPackage) {
            abort(400, 'This user does not have an active location membership.');
        }

        $boxFacility = $userLocation->location;

        $creditNote = UserInvoice::query()->create([
            'description' => $request->get('description'),
            'due_on' => $request->get('due_on'),
            'period_start' => $request->get('due_on'),
            'period_end' => $request->get('due_on'),
            'amount' => $request->get('amount'),
            'type' => InvoiceType::CREDIT_NOTE,
            'status' => InvoiceStatus::ISSUED,
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($boxFacility),
            'currency' => $tenant->memberCurrency->code,
            'user_to_facility_id' => $userLocation->getKey(),
            'user_to_package_id' => $userPackage->getKey(),
        ]);

        return new UserInvoiceResource($creditNote);
    }

    public function update(UserInvoice $userInvoice, UpdateCreditNoteRequest $request): UserInvoiceResource
    {
        $userInvoice->update($request->validated());

        return new UserInvoiceResource($userInvoice);
    }
}
