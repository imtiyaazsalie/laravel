<?php

namespace App\Exports;

use App\Enums\InvoicePaymentType;
use App\Models\Location;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\UserInvoicePayment;
use App\Services\POSService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PosSaleExport implements FromCollection, WithHeadings
{
    use Exportable;

    public function headings(): array
    {
        return [
            'Date',
            'Customer',
            'Sale ID',
            'Stock Item',
            'Location',
            'Item Price(inc tax)',
            'Quantity',
            'Subtotal(ex tax)',
            'Tax %',
            'Item Total',
        ];
    }

    public function collection(): Collection
    {
        $location = request()->input('filter.location_id') ? Location::query()->find(request()->input('filter.location_id')) : null;
        $status = request()->input('filter.status');
        $search = request()->input('filter.search');
        $startDate = request()->input('filter.start_date');
        $endDate = request()->input('filter.end_date');
        $sort = request()->input('sort');
        $order = request()->input('order');
        $sortArray = [];

        if ($sort) {
            $sortArray = [$sort];
        }

        $salesQueryBuilder = (new POSService())->getSalesQueryBuilder($location->tenant, $location, $status, $startDate, $endDate, $search);

        $filterByPurchaserName = false;

        if ($sortArray) {
            foreach ($sortArray as $value) {
                if ($value === 'purchaser.name') {
                    $filterByPurchaserName = true;
                    break;
                }

                $salesQueryBuilder->orderBy($value, $order);
            }
        }

        $sales = $salesQueryBuilder->with('saleItems')->get();

        // Filter by name
        if ($filterByPurchaserName) {
            if ($order == 'ASC') {
                $sales = $sales->sort(function ($a, $b) {
                    $purchaserName_a = $a->purchaser != null ? $a->purchaser->name : $a->purchaser_name;
                    $purchaserName_b = $b->purchaser != null ? $b->purchaser->name : $b->purchaser_name;

                    return strnatcasecmp($purchaserName_a, $purchaserName_b);
                });
            } else {
                $sales = $sales->sort(function ($a, $b) {
                    $purchaserName_a = $a->purchaser != null ? $a->purchaser->name : $a->purchaser_name;
                    $purchaserName_b = $b->purchaser != null ? $b->purchaser->name : $b->purchaser_name;

                    return strnatcasecmp($purchaserName_b, $purchaserName_a);
                });
            }
        }

        $totalCash = 0;
        $totalEFT = 0;
        $totalDebitOrder = 0;
        $totalCard = 0;
        $totalAdhoc = 0;
        $totalDiscount = 0;
        $totalTax = 0;
        $total = 0;

        $exportData = collect();

        /** @var PosSale $sale */
        foreach ($sales as $sale) {
            /** @var PosSaleItem $saleItem */
            foreach ($sale->saleItems as $saleItem) {
                $exportData->add([
                    0 => $sale->created_on->toDateString(),
                    1 => $sale->purchaser ? $sale->purchaser->full_name : $sale->purchaser_name,
                    2 => $sale->getKey(),
                    3 => $saleItem->stockItem?->name,
                    4 => $sale->location->name,
                    5 => number_format($saleItem->stockItem->selling_price, 2, '.', ''),
                    6 => $saleItem->quantity,
                    7 => number_format($saleItem->price_excluding_vat, 2, '.', ''),
                    8 => $saleItem->stockItem->vat,
                    9 => number_format($saleItem->price_including_vat, 2, '.', ''),
                ]);

                if ($sale->invoice && $sale->invoice->payments->isNotEmpty()) {
                    /** @var UserInvoicePayment $payment */
                    foreach ($sale->invoice->payments as $payment) {
                        if ($payment->type === InvoicePaymentType::EFT) {
                            $totalEFT += $payment->amount;
                        } elseif ($payment->type === InvoicePaymentType::CASH) {
                            $totalCash += $payment->amount;
                        } elseif ($payment->type === InvoicePaymentType::DEBIT_ORDER) {
                            $totalDebitOrder += $payment->amount;
                        } elseif ($payment->type === InvoicePaymentType::CARD) {
                            $totalCard += $payment->amount;
                        } else {
                            $totalAdhoc += $payment->amount;
                        }
                    }
                }

                $totalTax += $saleItem->tax_total;
            }

            $total += $sale->getTotalOfCart();
            $totalDiscount += $sale->discount_amount;
        }

        // Add empty line for spacing
        $exportData->add([
            0 => null,
            1 => null,
            2 => null,
            3 => null,
            4 => null,
            5 => null,
            6 => null,
            7 => null,
            8 => null,
            9 => null,
        ]);

        // Add the totals column
        $exportData->add([
            0 => null,
            1 => null,
            2 => 'Total EFT',
            3 => 'Total Cash',
            4 => 'Total Debit Order',
            5 => 'Total Card',
            6 => 'Total Ad-hoc',
            7 => 'Total Discount',
            8 => 'Total Tax',
            9 => 'Grand Total',
        ]);

        // Add the totals
        $exportData->add([
            0 => null,
            1 => null,
            2 => number_format($totalEFT, 2, '.', ''),
            3 => number_format($totalCash, 2, '.', ''),
            4 => number_format($totalDebitOrder, 2, '.', ''),
            5 => number_format($totalCard, 2, '.', ''),
            6 => number_format($totalAdhoc, 2, '.', ''),
            7 => number_format($totalDiscount, 2, '.', ''),
            8 => number_format($totalTax, 2, '.', ''),
            9 => number_format($total, 2, '.', ''),
        ]);

        return $exportData;
    }
}
