<?php

namespace App\Http\Controllers\API\POS;

use App\Enums\InvoicePaymentType;
use App\Enums\PosStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\POS\Sales\CancelPosSaleRequest;
use App\Http\Requests\POS\Sales\CreatePosSaleRequest;
use App\Http\Requests\POS\Sales\DeletePosSaleRequest;
use App\Http\Requests\POS\Sales\ReadPosSaleRequest;
use App\Http\Resources\PosSaleResource;
use App\Http\Resources\UserInvoiceResource;
use App\Models\Location;
use App\Models\LocationUser;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosStockItem;
use App\Models\UserInvoiceItem;
use App\Services\InvoiceService;
use App\Services\POSService;

class SaleController extends Controller
{
    public function __construct(private readonly POSService $posService)
    {
    }

    public function show(ReadPosSaleRequest $request, PosSale $sale)
    {
        return new PosSaleResource(
            $sale->loadMissing('purchaser', 'seller', 'invoice', 'location', 'saleItems.stockItem')
        );
    }

    public function store(CreatePosSaleRequest $request)
    {
        $location = Location::findOrFail($request->location_id);
        $userLocation = null;

        $sale = new PosSale();
        $sale->setAttribute('seller_id', auth()->user()->getAuthIdentifier());
        $sale->setAttribute('location_id', $request->get('location_id'));

        if ($request->user_id) {
            $sale->setAttribute('purchaser_id', $request->user_id);

            $userLocation = LocationUser::query()
                ->active()
                ->where('user_id', $request->get('user_id'))
                ->where('box_facility_id', $request->get('location_id'))
                ->first();

            if (! $userLocation) {
                abort(400, 'User does not belong to location.');
            }

        } elseif ($request->get('non_member')) {
            $sale
                ->setAttribute('purchaser_name', $request->input('non_member.name'))
                ->setAttribute('purchaser_email', $request->input('non_member.email'))
                ->setAttribute('purchaser_contact_number', $request->input('non_member.mobile'));
        }

        if ($request->has('note')) {
            $sale->setAttribute('note', $request->get('note'));
        }

        $sale->save();

        foreach ($request->get('products') as $product) {
            $stockItem = PosStockItem::query()
                ->where('box_facility_id', $request->get('location_id'))
                ->whereKey($product['id'])
                ->first();

            if (! $stockItem) {
                continue;
            }

            $saleItem = null;

            foreach ($sale->saleItems as $currentSaleItem) {
                if ($currentSaleItem->stockItem === $stockItem->getKey()) {
                    $saleItem = $currentSaleItem;
                }
            }

            if (! $saleItem) {
                $saleItem = new PosSaleItem();
                $saleItem->setAttribute('sale_id', $sale->getKey());
                $saleItem->setAttribute('stock_item_id', $stockItem->getKey());
                $saleItem->setAttribute('quantity', $product['quantity']);
                $saleItem->save();
            }
        }

        if ($request->has('discount') && is_numeric($request->get('discount'))) {
            $sale->update([
                'discount_amount' => $request->get('discount'),
            ]);
        }

        $sale->load('saleItems');

        switch ($request->get('sale_payment_type')) {
            case 'cash':
            case 'card':
            case 'eft':
                return new UserInvoiceResource($this->posService->processCashSale($sale, InvoicePaymentType::from($request->input('sale_payment_type')), $userLocation));
            case 'debitOrder':
                if (! $sale->purchaser()->exists()) {
                    abort(400, 'Debit order sales only available for registered debit order members.');
                }

                $invoice = (new InvoiceService)->getUpcomingDebitOrderInvoice($sale->purchaser, $location);

                if (! $invoice) {
                    abort(400, 'Sale could not be processed, debit-order invoice does not exist.');
                }

                // calculate amount, adding invoice items simultaneously
                $amount = $invoice->amount;

                foreach ($sale->saleItems as $saleItem) {
                    $subAmount = $saleItem->quantity * $saleItem->stockItem->selling_price;

                    if (is_int($saleItem->stockItem->stock_level) && $saleItem->stockItem->stock_level !== -1) {
                        $saleItem->stockItem->update([
                            'stock_level' => $saleItem->stockItem->stock_level - $saleItem->quantity,
                        ]);
                    }

                    UserInvoiceItem::create([
                        'description' => $saleItem->stockItem->name,
                        'code' => $saleItem->stockItem->sku,
                        'created_by_id' => auth()->user()->getAuthIdentifier(),
                        'discriminator' => 'pos',
                        'quantity' => $saleItem->quantity,
                        'price' => $saleItem->stockItem->selling_price,
                        'invoice_id' => $invoice->getKey(),
                        'amount' => $subAmount,
                        'vat' => $saleItem->stockItem->vat,
                    ]);

                    $amount = $amount + $subAmount;
                }

                if (! empty($sale->discount_amount)) {
                    UserInvoiceItem::create([
                        'description' => 'Discount',
                        'code' => '',
                        'created_by_id' => auth()->user()->getAuthIdentifier(),
                        'discriminator' => 'pos',
                        'quantity' => 1,
                        'price' => $sale->discount_amount,
                        'invoice_id' => $invoice->getKey(),
                        'amount' => -$sale->discount_amount,
                    ]);

                    $amount = $amount - $sale->discount_amount;
                }

                $invoice->setAttribute('amount', $amount);
                $invoice->userBatch->setAttribute('amount', $amount);

                $sale->setAttribute('status', PosStatus::PAID->value);
                $sale->setAttribute('invoice_id', $invoice->getKey());

                $sale->save();
                $invoice->save();
                $invoice->userBatch->save();

                $invoice->load('invoiceItems', 'payments');

                return new UserInvoiceResource($invoice);
            case 'adhocPayment':
                return new UserInvoiceResource($this->posService->processAdhocPaymentRequestSale($sale, $userLocation));
            case 'invoiceOnly':
                return new UserInvoiceResource($this->posService->processNonPaymentSale($sale, $userLocation));
            default:
                abort(400, 'Sale could not be processed. Something went wrong during the payment process.');
        }
    }

    public function cancel(CancelPosSaleRequest $request, PosSale $sale)
    {
        if ($sale->status !== PosStatus::CANCELLED) {
            $sale->update([
                'status' => PosStatus::CANCELLED,
            ]);
        }

        return new PosSaleResource(
            $sale->loadMissing('purchaser', 'seller', 'invoice', 'location', 'saleItems.stockItem')
        );
    }

    public function delete(DeletePosSaleRequest $request, PosSale $sale)
    {
        $sale->delete();

        return response()->noContent();
    }
}
