<?php

namespace App\Console\Commands;

use App\Models\UserInvoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculateInvoiceTotals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:recalculate-invoice-totals {--start=} {--end=} {--discriminator=} {--item-discriminator=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate invoice totals for a given date range and item discriminator.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dry = $this->option('dry-run');

        $discriminator = $this->option('discriminator');

        $itemDiscriminator = $this->option('item-discriminator');

        $start = $this->option('start')
            ? Carbon::parse($this->option('start'))->endOfDay()
            : today()->endOfDay();

        $end = $this->option('end')
            ? Carbon::parse($this->option('end'))->endOfDay()
            : today()->endOfDay();

        $invoices = UserInvoice::query()
            ->when($itemDiscriminator, function ($query) use ($itemDiscriminator) {
                $query->join('finance_invoice_items', 'finance_invoice_items.invoice_id', '=', 'finance_invoices.invoice_id')
                    ->where('finance_invoice_items.discriminator', '=', $itemDiscriminator);
            })
            ->when($discriminator, function ($query) use ($discriminator) {
                $query->where('finance_invoices.discriminator', '=', $discriminator);
            })
            ->with('userBatch.debitBatch')
            ->whereBetween('finance_invoices.created_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        $this->info("Found {$invoices->count()} invoices.");

        $invoices->each(function (UserInvoice $invoice) use ($dry) {

            $this->info("Recalculating invoice {$invoice->getKey()}");

            if (! $dry) {
                $invoice->recalculateTotal();

                //update user batch and debit batch totals
                if ($invoice->amount != $invoice->userBatch->amount) {

                    if ($invoice->userBatch->debitBatch->status !== 'pending') {
                        $this->error("[Submitted] Debit batch ID {$invoice->userBatch->debitBatch->getKey()} not in pending state, user batch ID {$invoice->userBatch->getKey()} amount doesn't match invoice ID {$invoice->getKey()} amount.");

                        return;
                    }

                    $invoice->userBatch()->update([
                        'amount' => $invoice->amount,
                    ]);

                    $invoice->userBatch->debitBatch->updateBatchTotal();
                }

            }
        });
    }
}
