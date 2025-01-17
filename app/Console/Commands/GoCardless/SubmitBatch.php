<?php

namespace App\Console\Commands\GoCardless;

use App\Enums\PaymentGateway;
use App\Jobs\GoCardless\SubmitDebitBatch;
use App\Models\DebitBatch;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SubmitBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:go-cardless:submit-debit-batch {--date=} {--charge-date-override=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit GoCardless batch.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->validateOptions()) {
            return Command::FAILURE;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today()->addDays(6);
        $chargeDateOverride = $this->option('charge-date-override') ? Carbon::parse($this->option('charge-date-override')) : null;

        $this->info("Date set to {$date->toDateString()}");

        if ($chargeDateOverride) {
            $this->info("Charge date override set to {$chargeDateOverride->toDateString()}");
        }

        DebitBatch::query()->unprocessedBatches($date, PaymentGateway::GO_CARDLESS)
            ->each(function ($debitBatch) use ($chargeDateOverride) {
                $this->info("Submitting debit batch {$debitBatch->getKey()}");
                SubmitDebitBatch::dispatch($debitBatch, $chargeDateOverride)->onQueue('finance');
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

        if ($this->option('charge-date-override')) {
            try {
                Carbon::parse($this->option('charge-date-override'));
            } catch (InvalidFormatException) {
                $this->error('The --charge-date-override option should be specified in the format --charge-date-override=Y-m-d');

                return false;
            }
        }

        return true;
    }
}
