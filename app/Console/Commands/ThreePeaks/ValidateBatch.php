<?php

namespace App\Console\Commands\ThreePeaks;

use App\Enums\PaymentGateway;
use App\Enums\ThreePeaksApiProcessingStatus;
use App\Jobs\ThreePeaks\ValidateDebitBatch;
use App\Models\DebitBatch;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ValidateBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:three-peaks:validate-debit-batch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate Three Peaks debit batches.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        DebitBatch::query()
            ->processed()
            ->whereNotIn('debit_batches.validation_status', [ThreePeaksApiProcessingStatus::VALIDATION_FAILED, ThreePeaksApiProcessingStatus::VALIDATION_PASSED])
            ->where('debit_batches.debit_batch_total', '>', 0)
            ->whereHas('location', function ($query) {
                $query->active()->where('payment_gateway_id', PaymentGateway::THREE_PEAKS->value);
            })
            ->whereHas('debitDayDate', function ($query) {
                $query->active()->where('debit_day_date', '<', today()->toDateString());
            })
            ->where(function (Builder $query) {
                $query->whereNotNull('debit_batches.subId')
                    ->where('debit_batches.subId', '!=', 0);
            })
            ->chunk(500, function ($batches) {
                $batches->each(function ($batch) {
                    ValidateDebitBatch::dispatch($batch)->onQueue('finance');
                });
            });

        return Command::SUCCESS;
    }
}
