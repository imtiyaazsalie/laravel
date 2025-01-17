<?php

namespace App\Console\Commands\Netcash;

use App\Jobs\Netcash\ReconStatement;
use App\Models\DebitBatchStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class ReconNetcashStatements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:netcash:reconcile-batch-statements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile Netcash batch statements.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $jobs = [];

        DebitBatchStatement::query()
            ->whereNotNull('content')
            ->whereNotNull('dt_downloaded')
            ->whereNull('dt_reconciled')
            ->each(function ($statement) use (&$jobs) {
                $jobs[] = new ReconStatement($statement);
            });

        Bus::chain($jobs)->onQueue('finance')->dispatch();

        return Command::SUCCESS;
    }
}
