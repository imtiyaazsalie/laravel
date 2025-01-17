<?php

namespace App\Console\Commands\ThreePeaks;

use App\Enums\PaymentGateway;
use App\Jobs\ThreePeaks\SubmitBatch;
use App\Models\DebitBatch;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SubmitDebitBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:three-peaks:submit-debit-batch {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit Three Peaks debit batches.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today()->addDays(5);

        DebitBatch::query()
            ->unprocessedBatches($date, PaymentGateway::THREE_PEAKS)
            ->chunk(500, function ($batches) use ($date) {
                foreach ($batches as $batch) {
                    SubmitBatch::dispatch($batch, $date)->onQueue('finance');
                }
            });

        return $this::SUCCESS;
    }
}
