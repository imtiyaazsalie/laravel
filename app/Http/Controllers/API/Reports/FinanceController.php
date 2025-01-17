<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\Finance\DebtorsRequest;
use App\Http\Requests\Reports\Finance\DiscountRequest;
use App\Http\Requests\Reports\Finance\PackageSalesAndRevenueMetricsRequest;
use App\Http\Requests\Reports\Finance\PaymentRequest;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function __construct(private readonly ReportsService $reportService)
    {
    }

    public function debtors(DebtorsRequest $request): JsonResponse
    {
        return response()->json($this->reportService->getDebtorsDetailForPeriod($request));
    }

    public function getPackagesSalesAndRevenueMetrics(PackageSalesAndRevenueMetricsRequest $request): JsonResponse
    {
        return response()->json($this->reportService->getPackagesSalesAndRevenueMetrics($request));
    }

    public function discount(DiscountRequest $request): JsonResponse
    {
        return response()->json($this->reportService->getDiscountsData($request));
    }

    public function paymentTypes(PaymentRequest $request): JsonResponse
    {
        return response()->json($this->reportService->getPaymentsData($request));
    }
}
