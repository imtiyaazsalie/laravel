<?php

namespace App\Console\Commands\Netcash;

use App\Enums\PaymentGateway;
use App\Jobs\Netcash\RequestStatement;
use App\Models\DebitBatch;
use Illuminate\Console\Command;

class RequestNetcashStatements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:netcash:request-batch-statements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Request Netcash batch statements.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = today()->subDays(40);

        DebitBatch::query()
            ->joinRelationship('debitDayDate.debitDay')
            ->where('debit_batches.is_processed', true)
            ->where('debit_day_dates.debit_day_date', '>=', $date->toDateString())
            ->where('debit_day_dates.debit_day_date', '<=', today()->toDateString())
            ->where('debit_day_dates.is_active', true)
            ->where('debit_days.is_active', true)
            ->whereRelation('location', function ($query) {
                $query->where('payment_gateway_id', '=', PaymentGateway::SAGE_PAY_V3)
                    ->where('is_active', '=', true);
            })
            ->chunk(500, function ($debitBatches) {
                $debitBatches->each(function ($debitBatch) {
                    RequestStatement::dispatch($debitBatch)->onQueue('finance');
                });
            });

        return Command::SUCCESS;
    }
}
