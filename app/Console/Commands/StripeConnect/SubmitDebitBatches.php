<?php

namespace App\Console\Commands\StripeConnect;

use App\Enums\PaymentGateway;
use App\Jobs\StripeConnect\SubmitDebitBatch;
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
    protected $signature = 'app:stripe-connect:submit-debit-batches {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit stripe-connect debit batches.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->validateOptions()) {
            return Command::FAILURE;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today()->addDays(6);

        DebitBatch::query()
            ->unprocessedBatches($date, PaymentGateway::STRIPE_CONNECT)
            ->chunk(500, function ($debitBatches) {
                foreach ($debitBatches as $debitBatch) {
                    SubmitDebitBatch::dispatch($debitBatch)->onQueue('finance');
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
