<?php

namespace App\Services\PaymentGateways;

use App\Models\UserInvoicePayment;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentService
{
    public function getLeadPaymentsQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(UserInvoicePayment::class)
            ->withoutGlobalScopes()
            ->select('*')
            ->addSelect('finance_payments.type as type')
            ->join('finance_invoices', 'finance_invoices.invoice_id', '=', 'finance_payments.invoice_id')
            ->join('box_facility', 'finance_invoices.box_facility_id', '=', 'box_facility.box_facility_id')
            ->where('finance_payments.deleted', false)
            ->where('finance_invoices.deleted', false)
            ->whereNotNull('finance_invoices.lead_member_id')
            ->orderByDesc('finance_payments.date_time')
            ->distinct()
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_facility.box_id'),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $query->whereBetween('finance_payments.date_time', $value);
                }),
                AllowedFilter::exact('location_id', 'box_facility.box_facility_id'),
                AllowedFilter::exact('type', 'finance_payments.type'),
            ])
            ->allowedIncludes([
                'invoice',
            ]);
    }

    public function getPaymentsQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(UserInvoicePayment::class)
            ->withoutGlobalScopes()
            ->join('user_to_facility', 'user_to_facility.user_to_facility_id', '=', 'finance_payments.user_to_facility_id')
            ->join('box_facility', 'user_to_facility.box_facility_id', '=', 'box_facility.box_facility_id')
            ->join('finance_invoices', 'finance_payments.invoice_id', '=', 'finance_invoices.invoice_id')
            ->where('finance_payments.deleted', false)
            ->where('finance_invoices.deleted', false)
            ->orderByDesc('finance_payments.date_time')
            ->select('finance_payments.*')
            ->addSelect('finance_payments.type as type')
            ->distinct()
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_facility.box_id'),
                AllowedFilter::callback('between', function (Builder $query, $value) {
                    $query->whereBetween('finance_payments.date_time', $value);
                }),
                AllowedFilter::exact('location_id', 'box_facility.box_facility_id'),
                AllowedFilter::exact('type', 'finance_payments.type'),
            ])
            ->allowedIncludes([
                'invoice',
            ]);
    }
}
