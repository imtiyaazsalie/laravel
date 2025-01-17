<?php

namespace App\Http\Controllers\API\POS;

use App\Exports\PosSaleExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\POS\Reports\ExportSalesReportRequest;
use App\Http\Requests\POS\Reports\SalesByStockItemReportRequest;
use App\Http\Requests\POS\Reports\SalesDetailsReportRequest;
use App\Http\Requests\POS\Reports\SalesReportRequest;
use App\Http\Requests\POS\Reports\StockItemsReportRequest;
use App\Models\Location;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosStockItem;
use App\Models\Tenant;
use App\Models\UserInvoice;
use App\Services\POSService;
use App\Services\TenantUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function sales(SalesReportRequest $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->input('filter.tenant_id'));
        $location = $request->input('filter.location_id') ? Location::query()->find($request->input('filter.location_id')) : null;
        $status = $request->input('filter.status');
        $search = $request->input('filter.search');
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $sort = $request->input('sort');
        $order = $request->input('order');
        $sortArray = [];

        if (! $startDate || ! $endDate) {
            $startDate = date('Y-m-d', strtotime('first day of this month'));
            $endDate = date('Y-m-d', strtotime('last day of this month'));
        }

        if ($sort) {
            $sortArray = [$sort];
        }

        $authUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $tenant);

        if ($authUser?->isLocationAdmin()) {
            $location = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $tenant)?->location;
        }

        $salesQueryBuilder = (new POSService())->getSalesQueryBuilder($tenant, $location, $status, $startDate, $endDate, $search);

        $filterByPurchaserName = false;

        if ($sortArray) {
            foreach ($sortArray as $index => $value) {
                if ($value === 'purchaser.name') {
                    $filterByPurchaserName = true;
                    break;
                }

                if ($index === 0) {
                    $salesQueryBuilder->orderBy($value, $order);
                } else {
                    $salesQueryBuilder->orderBy($value, $order);
                }
            }
        }

        $sales = $salesQueryBuilder->with('saleItems')->get();

        // Filter by name
        if ($filterByPurchaserName) {
            if ($order == 'ASC') {
                $sales = $sales->sort(function ($a, $b) {
                    $purchaserName_a = $a->purchaser ? $a->purchaser->name : $a->purchaser_name;
                    $purchaserName_b = $b->purchaser ? $b->purchaser->name : $b->purchaser_name;

                    return strnatcasecmp($purchaserName_a, $purchaserName_b);
                });
            } else {
                $sales = $sales->sort(function ($a, $b) {
                    $purchaserName_a = $a->purchaser ? $a->purchaser->name : $a->purchaser_name;
                    $purchaserName_b = $b->purchaser ? $b->purchaser->name : $b->purchaser_name;

                    return strnatcasecmp($purchaserName_b, $purchaserName_a);
                });
            }
        }

        // Paginate data
        $serializedData = [];

        /** @var PosSale $sale */
        foreach ($sales as $sale) {
            if ($sale->purchaser) {
                $purchaser = [
                    'id' => $sale->purchaser->getKey(),
                    'name' => $sale->purchaser->full_name,
                    'email' => $sale->purchaser->email,
                    'mobile' => $sale->purchaser->mobile,
                ];
            } else {
                $purchaser = [
                    'name' => $sale->purchaser_name,
                    'email' => $sale->purchaser_email,
                    'mobile' => $sale->purchaser_contact_number,
                ];
            }

            $serializedData[] = [
                'id' => $sale->getKey(),
                'created_on' => $sale->created_on->toDateTimeString(),
                'location' => [
                    'id' => $sale->location->getKey(),
                    'name' => $sale->location->name,
                ],
                'purchaser' => $purchaser,
                'seller' => [
                    'id' => $sale->seller->getKey(),
                    'name' => $sale->seller->full_name,
                    'email' => $sale->seller->email,
                    'mobile' => $sale->seller->mobile,
                ],
                'cart_size' => $sale->saleItems->sum('quantity'),
                'cart_cost_price' => $sale->getTotalCostPriceOfCart(),
                'cart_amount' => $sale->getTotalOfCart(),
                'status' => $sale->status->value,
                'invoice_id' => $sale->invoice instanceof UserInvoice ? $sale->invoice->getKey() : null,
            ];
        }

        return response()->json(collect($serializedData)->paginate($request->per_page ?? 25));
    }

    public function getSaleDetails(PosSale $sale, SalesDetailsReportRequest $request): JsonResponse
    {
        $location = $request->input('filter.location_id') ? Location::query()->find($request->input('filter.location_id')) : null;

        $saleDetails = [];
        $saleSaleItems = (new POSService())->getSaleItemsBySale($sale, $location->tenant, $location, $request->input('filter.start_date'), $request->input('filter.end_date'));

        /** @var PosSaleItem $saleItem */
        foreach ($saleSaleItems as $saleItem) {
            $totalCostPrice = $saleItem->stockItem->cost_price * $saleItem->quantity;

            $saleDetails[] = [
                'id' => $saleItem->getKey(),
                'name' => ucfirst($saleItem->stockItem->name),
                'quantity' => $saleItem->quantity,
                'costPrice' => $totalCostPrice,
                'sellingPrice' => $saleItem->stockItem->selling_price,
            ];
        }

        return response()->json($saleDetails);
    }

    public function exportSalesReport(ExportSalesReportRequest $request): JsonResponse
    {
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');

        $filename = "pos-sales-export/sales-$startDate-$endDate.csv";

        Excel::store(new PosSaleExport(), $filename, 'tmp', \Maatwebsite\Excel\Excel::CSV);

        return response()->json([
            'file' => Storage::disk('tmp')->temporaryUrl($filename, now()->addMinutes(5)),
        ]);
    }

    public function stockItems(StockItemsReportRequest $request): JsonResponse
    {
        $location = $request->input('filter.location_id') ? Location::query()->find($request->input('filter.location_id')) : null;
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');

        if (! $startDate || ! $endDate) {
            $startDate = date('Y-m-d', strtotime('first day of this month'));
            $endDate = date('Y-m-d', strtotime('last day of this month'));
        }

        $authUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $location->tenant);

        if ($authUser?->isLocationAdmin()) {
            $locations = [(new TenantUserService())->getLocationUserByTenant($request->user(), $location->tenant)->box_facility_id];
        } else {
            $locations = $authUser?->tenant->locations->pluck('box_facility_id');
        }

        // get a list of products
        $stockItems = (new POSService())->getStockItemsForBoxFacilities($location ? [$location->getKey()] : $locations);

        // Paginate data
        $serializedData = collect();

        /** @var PosStockItem $stockItem */
        foreach ($stockItems as $stockItem) {

            $stockItemDetails = (new POSService())
                ->getStockItemDetails(
                    $stockItem,
                    (new POSService())->getSaleItemsByStockItem($stockItem, $location->tenant, $location, $startDate, $endDate)
                );

            $serializedData->add([
                'stock_item' => [
                    'id' => $stockItem->getKey(),
                    'name' => $stockItem->name,
                    'description' => $stockItem->description,
                    'cost_price' => $stockItem->cost_price,
                    'selling_price' => $stockItem->selling_price,
                    'vat' => $stockItem->vat,
                    'sku' => $stockItem->sku ? $stockItem->sku : null,
                    'stock_level' => $stockItem->stock_level,
                    'category' => $stockItem->category,
                ],
                'quantity' => $stockItemDetails['quantity'],
                'total_cost' => $stockItemDetails['totalCost'],
                'total_amount' => $stockItemDetails['totalAmount'],
            ]);
        }

        return response()->json($serializedData->paginate());
    }

    public function getStockItemDetails(PosStockItem $stockItem, SalesByStockItemReportRequest $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->input('filter.tenant_id'));

        $location = $request->input('filter.location_id') ? Location::query()->find($request->input('filter.location_id')) : null;

        $stockItemDetails = [];
        $sales = (new POSService())->getSalesByStockItem($stockItem, $tenant, $location, $request->input('filter.start_date'), $request->input('filter.end_date'));

        /** @var PosSale $sale */
        foreach ($sales as $sale) {
            if ($sale->purchaser) {
                $purchaser = [
                    'id' => $sale->purchaser->getKey(),
                    'name' => $sale->purchaser->full_name,
                    'email' => $sale->purchaser->email,
                    'mobile' => $sale->purchaser->mobile,
                ];
            } else {
                $purchaser = [
                    'name' => $sale->purchaser_name,
                    'email' => $sale->purchaser_email,
                    'mobile' => $sale->purchaser_contact_number,
                ];
            }

            $stockItemDetails[] = [
                'id' => $sale->getKey(),
                'created_on' => $sale->created_on->format('Y-m-d H:i:s'),
                'location' => [
                    'id' => $sale->location->getKey(),
                    'name' => $sale->location->name,
                ],
                'purchaser' => $purchaser,
                'seller' => [
                    'id' => $sale->seller->getKey(),
                    'name' => $sale->seller->full_name,
                    'email' => $sale->seller->email,
                    'mobile' => $sale->seller->mobile,
                ],
                'cart_size' => $sale->saleItems->sum('quantity'),
                'cart_cost_price' => $sale->getTotalCostPriceOfCart(),
                'cart_amount' => $sale->getTotalOfCart(),
            ];
        }

        return response()->json($stockItemDetails);
    }
}
