<?php

namespace App\Services;

use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PosStatus;
use App\Models\Location;
use App\Models\LocationUser;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosStockItem;
use App\Models\Tenant;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Models\UserInvoicePayment;
use Illuminate\Database\Eloquent\Collection;

class POSService
{
    public function processCashSale(PosSale $sale, ?InvoicePaymentType $paymentType = InvoicePaymentType::CASH, ?LocationUser $locationUser = null): UserInvoice
    {
        $invoice = UserInvoice::create([
            'created_by_id' => auth()->user()->getAuthIdentifier(),
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($sale->location),
            'currency' => $sale->location->tenant->tenantCurrency->code,
            'description' => 'POS invoice',
            'note' => $sale->note,
            'period_start' => now(),
            'period_end' => now(),
            'due_on' => now(),
            'status' => InvoiceStatus::PAID,
            'type' => InvoiceType::INVOICE,
            'location_id' => $sale->location_id,
        ]);

        if ($sale->purchaser()->exists()) {
            $invoice->setAttribute('user_location_id', $locationUser?->getKey());
        } else {
            $invoice->setAttribute('non_member_name', $sale->purchaser_name);
            $invoice->setAttribute('non_member_email', $sale->purchaser_email);
            $invoice->setAttribute('note', $sale->note.PHP_EOL.PHP_EOL.$sale->purchaser_name.PHP_EOL.$sale->purchaser_email.PHP_EOL.$sale->purchaser_contact_number);
        }

        // calculate amount, adding invoice items simultaneously
        $amount = 0.00;

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

        $invoice->save();

        // generate payment
        $payment = new UserInvoicePayment();

        $payment->setAttribute('amount', $amount);
        $payment->setAttribute('created_by_id', auth()->user()->getAuthIdentifier());
        $payment->setAttribute('currency', $sale->location->tenant->tenantCurrency->code);
        $payment->setAttribute('date_time', now());
        $payment->setAttribute('invoice_id', $invoice->getKey());
        $payment->setAttribute('type', $paymentType);

        if ($sale->purchaser()->exists()) {
            $payment->setAttribute('user_location_id', $locationUser?->getKey());
            $payment->setAttribute('reference', 'POS sale cash payment (#'.$sale->getKey().')');
        } else {
            $payment->setAttribute('reference', 'POS sale cash payment (#'.$sale->getKey().') - '.$sale->purchaser_name.'('.$sale->purchaser_email.'/'.$sale->purchaser_mobile.')');
        }

        $sale->setAttribute('status', PosStatus::PAID);
        $sale->setAttribute('invoice_id', $invoice->getKey());

        $sale->save();
        $payment->save();

        if ($sale->purchaser()->exists()) {
            (new InvoiceService())->sendUserInvoice($invoice, $sale->location);
        } else {
            (new InvoiceService())->sendUserInvoice($invoice, $sale->location, $sale->purchaser_email, $sale->purchaser_name);
        }

        $invoice->load('invoiceItems', 'payments');

        return $invoice;
    }

    public function processAdhocPaymentRequestSale(PosSale $sale, ?LocationUser $locationUser = null): UserInvoice
    {
        $invoice = UserInvoice::create([
            'created_by_id' => auth()->user()->getAuthIdentifier(),
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($sale->location),
            'currency' => $sale->location->tenant->tenantCurrency->code,
            'description' => 'POS invoice',
            'note' => $sale->note,
            'period_start' => now(),
            'period_end' => now(),
            'due_on' => now(),
            'status' => InvoiceStatus::UNPAID,
            'type' => InvoiceType::INVOICE,
            'location_id' => $sale->location_id,
        ]);

        if ($sale->purchaser()->exists()) {
            $invoice->setAttribute('user_location_id', $locationUser?->getKey());
        } else {
            $invoice->setAttribute('non_member_name', $sale->purchaser_name);
            $invoice->setAttribute('non_member_email', $sale->purchaser_email);
            $invoice->setAttribute('note', $sale->note.PHP_EOL.PHP_EOL.$sale->purchaser_name.PHP_EOL.$sale->purchaser_email.PHP_EOL.$sale->purchaser_contact_number);
        }

        // calculate amount, adding invoice items simultaneously
        $amount = 0.00;

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

        $sale->setAttribute('status', PosStatus::INVOICED);
        $sale->setAttribute('invoice_id', $invoice->getKey());

        $sale->save();
        $invoice->save();

        $invoice->load('invoiceItems', 'payments');

        return $invoice;
    }

    public function processNonPaymentSale(PosSale $sale, ?LocationUser $locationUser = null): UserInvoice
    {
        $invoice = UserInvoice::create([
            'created_by_id' => auth()->user()->getAuthIdentifier(),
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($sale->location),
            'currency' => $sale->location->tenant->tenantCurrency->code,
            'description' => 'POS invoice',
            'note' => $sale->note,
            'period_start' => now(),
            'period_end' => now(),
            'due_on' => now(),
            'status' => InvoiceStatus::UNPAID,
            'type' => InvoiceType::INVOICE,
            'location_id' => $sale->location->getKey(),
        ]);

        if ($sale->purchaser()->exists()) {
            $invoice->setAttribute('user_location_id', $locationUser?->getKey());
        } else {
            $invoice->setAttribute('non_member_name', $sale->purchaser_name);
            $invoice->setAttribute('non_member_email', $sale->purchaser_email);
            $invoice->setAttribute('note', $sale->note.PHP_EOL.PHP_EOL.$sale->purchaser_name.PHP_EOL.$sale->purchaser_email.PHP_EOL.$sale->purchaser_contact_number);
        }

        // calculate amount, adding invoice items simultaneously
        $amount = 0.00;

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

        $sale->setAttribute('status', PosStatus::INVOICED);
        $sale->setAttribute('invoice_id', $invoice->getKey());

        $sale->save();
        $invoice->save();

        $invoice->load('invoiceItems', 'payments');

        if ($sale->purchaser()->exists()) {
            (new InvoiceService())->sendUserInvoice($invoice, $sale->location);
        } else {
            (new InvoiceService())->sendUserInvoice($invoice, $sale->location, $sale->purchaser_email, $sale->purchaser_name);
        }

        return $invoice;
    }

    public function getSalesQueryBuilder(Tenant $box, ?Location $location = null, ?string $status = null, ?string $startDate = null, ?string $endDate = null, ?string $search = null)
    {
        $qb = PosSale::query()
            ->select('pos_sales.*')
            ->withoutGlobalScopes()
            ->from('pos_sales')
            ->join('users as seller', 'pos_sales.seller_id', '=', 'seller.user_id')
            ->join('box_facility as bf', 'bf.box_facility_id', '=', 'pos_sales.box_facility_id')
            ->join('boxes as b', 'b.box_id', '=', 'bf.box_id')
            ->where('bf.box_id', '=', $box->getKey())
            ->where('pos_sales.deleted', '=', false)
            ->orderByDesc('pos_sales.created_on');

        if ($location) {
            $qb->where('pos_sales.box_facility_id', $location->getKey());
        }

        if ($status) {
            $qb->where('pos_sales.status', $status);
        }

        if ($startDate && $endDate) {
            $start = new \DateTime($startDate);
            $start->setTime(0, 0, 0);
            $start = $start->format('Y-m-d H:i:s');

            $end = new \DateTime($endDate);
            $end->setTime(23, 59, 59);
            $end = $end->format('Y-m-d H:i:s');

            $qb->whereBetween('pos_sales.created_on', [$start, $end]);
        }

        return $qb;
    }

    public function getSaleItemsBySale(PosSale $sale, Tenant $box, ?Location $location = null, ?string $startDate = null, ?string $endDate = null): Collection|array
    {
        $qb = PosSaleItem::query()
            ->from('pos_sale_items', 'si')
            ->join('pos_sales as s', 's.sale_id', '=', 'si.sale_id')
            ->join('box_facility as bf', 'bf.box_facility_id', '=', 's.box_facility_id')
            ->join('boxes as b', 'b.box_id', '=', 'bf.box_id')
            ->where('s.sale_id', $sale->getKey())
            ->where('bf.box_id', $box->getKey())
            ->where('s.deleted', false)
            ->orderBy('s.created_on');

        if ($location instanceof Location) {
            $qb->where('s.box_facility_id', $location->getKey());
        }

        if ($startDate && $endDate) {
            $start = new \DateTime($startDate);
            $start->setTime(0, 0, 0);
            $start = $start->format('Y-m-d H:i');
            $end = new \DateTime($endDate);
            $end->setTime(23, 59, 59);
            $end = $end->format('Y-m-d H:i');

            $qb->whereBetween('s.created_on', [$start, $end]);

        }

        return $qb->get();
    }

    public function getStockItemsForBoxFacilities($locations, $search = null, $type = 'stock')
    {
        $qb = PosStockItem::query()
            ->withoutGlobalScopes()
            ->from('pos_stock_items', 'si')
            ->where('si.deleted', false)
            ->orderBy('si.category');

        if ($type == 'topups') {
            $qb->join('packages as p', 'p.package_id', 'si.sessions_released_by_id');
        } else {
            $qb->whereIn('si.box_facility_id', $locations);
        }

        if ($search) {
            $qb->where('si.name', 'LIKE', '%'.$search.'%');
        }

        return $qb->get();
    }

    public function getStockItemDetails(PosStockItem $stockItem, Collection|array $stockItemSalesItems)
    {
        $quantity = 0;
        $totalCost = 0;
        $totalAmount = 0;

        /** @var PosSaleItem $saleItem */
        foreach ($stockItemSalesItems as $saleItem) {
            $quantity += $saleItem->quantity;
        }

        $totalCost += $stockItem->cost_price * $quantity;
        $totalAmount += $stockItem->selling_price * $quantity;

        return [
            'quantity' => $quantity,
            'totalCost' => $totalCost,
            'totalAmount' => $totalAmount,
        ];
    }

    public function getSaleItemsByStockItem(PosStockItem $stockItem, Tenant $box, ?Location $location = null, ?string $startDate = null, ?string $endDate = null)
    {
        $qb = PosSaleItem::query()
            ->from('pos_sale_items', 'si')
            ->join('pos_sales as s', 's.sale_id', '=', 'si.sale_id')
            ->join('pos_stock_items as stockItem', 'si.stock_item_id', '=', 'stockItem.stock_item_id')
            ->join('box_facility as bf', 'bf.box_facility_id', '=', 's.box_facility_id')
            ->join('boxes as b', 'b.box_id', '=', 'bf.box_id')
            ->where('bf.box_id', '=', $box->getKey())
            ->where('stockItem.stock_item_id', '=', $stockItem->getKey())
            ->where('s.deleted', false)
            ->orderBy('s.created_on');

        if ($location instanceof Location) {
            $qb->where('s.box_facility_id', $location->getKey());
        }

        if ($startDate && $endDate) {
            $start = new \DateTime($startDate);
            $start->setTime(0, 0, 0);
            $start = $start->format('Y-m-d H:i');
            $end = new \DateTime($endDate);
            $end->setTime(23, 59, 59);
            $end = $end->format('Y-m-d H:i');
            $qb->whereBetween('s.created_on', [$start, $end]);
        }

        return $qb->get();
    }

    public function getSalesByStockItem(PosStockItem $stockItem, Tenant $box, ?Location $location = null, ?string $startDate = null, ?string $endDate = null)
    {
        $qb = PosSale::query()
            ->withoutGlobalScopes()
            ->from('pos_sales', 's')
            ->join('pos_sale_items as saleItem', 'saleItem.sale_id', '=', 's.sale_id')
            ->join('pos_stock_items as stockItem', 'stockItem.stock_item_id', '=', 'saleItem.stock_item_id')
            ->join('box_facility as bf', 'bf.box_facility_id', '=', 's.box_facility_id')
            ->join('boxes as b', 'b.box_id', '=', 'bf.box_id')
            ->where('bf.box_id', $box->getKey())
            ->where('stockItem.stock_item_id', $stockItem->getKey())
            ->where('s.deleted', false)
            ->orderBy('s.created_on');

        if ($location instanceof Location) {
            $qb->where('s.box_facility_id', $location->getKey());
        }

        if ($startDate && $endDate) {
            $start = new \DateTime($startDate);
            $start->setTime(0, 0, 0);
            $start = $start->format('Y-m-d H:i');
            $end = new \DateTime($endDate);
            $end->setTime(23, 59, 59);
            $end = $end->format('Y-m-d H:i');
            $qb->whereBetween('s.created_on', [$start, $end]);
        }

        return $qb->get();
    }
}
