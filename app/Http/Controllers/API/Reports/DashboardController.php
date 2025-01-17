<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\Dashboard\AccountMetricsRequest;
use App\Http\Requests\Reports\Dashboard\DebtorsSummaryRequest;
use App\Http\Requests\Reports\Dashboard\InactiveMandatesCountRequest;
use App\Http\Requests\Reports\Dashboard\LeadMetricsRequest;
use App\Http\Requests\Reports\Dashboard\MemberMetricDetailsRequest;
use App\Http\Requests\Reports\Dashboard\MemberMetricsRequest;
use App\Http\Requests\Reports\Dashboard\MemberMovementRequest;
use App\Http\Requests\Reports\Dashboard\MembersByPaymentTypeRequest;
use App\Http\Requests\Reports\Dashboard\MembersPerPackageRequest;
use App\Http\Requests\Reports\Dashboard\MembersPerProgrammeRequest;
use App\Http\Requests\Reports\Dashboard\PaymentsByTagRequest;
use App\Http\Requests\Reports\Dashboard\PaymentsByTypeRequest;
use App\Http\Requests\Reports\Dashboard\SalesByTypeRequest;
use App\Http\Requests\Reports\Dashboard\SchedulingMetricsRequest;
use App\Http\Requests\Reports\Dashboard\TotalSalesAndPaymentRequest;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(protected ReportsService $reportsService)
    {
    }

    public function memberMetrics(MemberMetricsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getMembersMetrics($request));
    }

    public function getMembersDetailsForMetric(MemberMetricDetailsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getMembersDetailsForMetric($request));
    }

    public function getMembersPerPackage(MembersPerPackageRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getMembersPerPackage($request));
    }

    public function getMembersPerProgramme(MembersPerProgrammeRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getMembersPerProgramme($request));
    }

    public function getSchedulingMetrics(SchedulingMetricsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getSchedulingMetrics($request));
    }

    public function getAccountsMetrics(AccountMetricsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getAccountsMetrics($request));
    }

    public function getTotalSalesAndPayments(TotalSalesAndPaymentRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getTotalSalesAndPaymentsMetrics($request));
    }

    public function getSalesByType(SalesByTypeRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getSalesByTypeData($request));
    }

    public function getPaymentsByType(PaymentsByTypeRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getPaymentsByTypeData($request));
    }

    public function getPaymentsByTag(PaymentsByTagRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getPaymentsByTagData($request));
    }

    public function getMembersByPaymentType(MembersByPaymentTypeRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getMembersByPaymentTypeData($request));
    }

    public function getLeadsMetrics(LeadMetricsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getLeadsMetricsData($request));
    }

    public function getDebtorsSummaryByMonth(DebtorsSummaryRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getDebtorsSummaryByPeriods($request));
    }

    public function getMemberMovement(MemberMovementRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getMemberMovementData($request));
    }

    public function inactiveMandatesCount(InactiveMandatesCountRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getInactiveMandatesCount($request));
    }
}
