<?php

namespace App\Console\Commands\ThreePeaks;

use App\Enums\PaymentGateway;
use App\Jobs\ThreePeaks\ReconcileBatch;
use App\Models\DebitBatch;
use Illuminate\Console\Command;

class ReconBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:three-peaks:reconcile-debit-batch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile Three Peaks debit batches.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        DebitBatch::query()
            ->processed()
            ->where('debit_batches.debit_batch_total', '>', 0)
            ->whereNotNull('debit_batches.subId')
            ->whereHas('debitDayDate', function ($query) {
                $query->active()->where('debit_day_date', '>=', today()->subDays(60)->toDateString());
            })
            ->whereHas('location', function ($query) {
                $query->active()->where('payment_gateway_id', PaymentGateway::THREE_PEAKS);
            })
            ->chunk(500, function ($batches) {
                $batches->each(function ($batch) {
                    ReconcileBatch::dispatch($batch)->onQueue('finance');
                });
            });

        return Command::SUCCESS;
    }
}
