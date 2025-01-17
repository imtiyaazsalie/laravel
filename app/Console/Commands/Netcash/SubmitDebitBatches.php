<?php

namespace App\Console\Commands\Netcash;

use App\Enums\PaymentGateway;
use App\Jobs\Netcash\SubmitBatch;
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
    protected $signature = 'app:netcash:submit-debit-batch {--date=} {--same-day}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit Netcash debit batches.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->validateOptions()) {
            return Command::FAILURE;
        }

        if ($this->option('date')) {
            $date = Carbon::parse($this->option('date'));
        } else {
            $date = $this->option('same-day') ? today() : today()->addDays(5);
        }

        $this->info("Date set to {$date->toDateString()}");

        $type = $this->option('same-day') ? 'SameDay' : 'TwoDay';

        DebitBatch::query()
            ->unprocessedBatches($date, PaymentGateway::SAGE_PAY_V3, isSameDay: $type === 'SameDay')
            ->each(function ($debitBatch) use ($date, $type) {
                $this->info("Submitting debit batch {$debitBatch->getKey()}");
                SubmitBatch::dispatch($debitBatch, $date, $type)->onQueue('finance');
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
