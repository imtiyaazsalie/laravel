<?php

namespace App\Console\Commands\Netcash;

use App\Jobs\Netcash\DownloadStatement;
use App\Models\DebitBatchStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class DownloadNetcashStatements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:netcash:download-batch-statements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download Netcash batch statements.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $jobs = [];
        $batches = [];

        DebitBatchStatement::query()
            ->join('debit_batches', 'debit_batches.debit_batch_id', '=', 'debit_batch_statements.debit_batch_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'debit_batches.box_facility_id')
            ->where('box_facility.is_active', '=', 1)
            ->whereNotNull('polling_id')
            ->whereNull('dt_downloaded')
            ->chunk(500, function ($debitBatchStatements) use (&$batches, &$jobs) {
                $debitBatchStatements->each(function ($debitBatchStatement) use (&$batches, &$jobs) {
                    $key = $debitBatchStatement->debit_batch_id.'_'.$debitBatchStatement->date->format('Ymd');

                    if (isset($batches[$key])) {
                        $debitBatchStatement->delete();

                        return;
                    }

                    $batches[$key] = true;

                    $jobs[] = new DownloadStatement($debitBatchStatement);
                });
            });

        Bus::chain($jobs)->onQueue('finance')->dispatch();

        return Command::SUCCESS;
    }
}
