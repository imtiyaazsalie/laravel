<?php

namespace App\Console\Commands\Stripe;

use App\Enums\PaymentGateway;
use App\Jobs\Stripe\SubmitBatch;
use App\Models\DebitBatch;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SubmitDebitBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:stripe:submit-debit-batches {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit Stripe debit batches.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! $this->validateOptions()) {
            return Command::FAILURE;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today()->addDays(6);

        DebitBatch::query()
            ->unprocessedBatches($date, PaymentGateway::STRIPE)
            ->chunk(500, function ($debitBatches) {
                foreach ($debitBatches as $debitBatch) {
                    SubmitBatch::dispatch($debitBatch)->onQueue('finance');
                }
            });

        return Command::SUCCESS;
    }

    public function validateOptions(): bool
    {
        if ($this->option('date')) {
            try {
                Carbon::parse($this->option('date'));
            } catch (InvalidFormatException) {
                $this->error('The --date option should be specified in the format --date=Y-m-d');

                return false;
            }
        }

        return true;
    }
}
