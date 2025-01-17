<?php

namespace App\Jobs\Netcash;

use App\Models\DebitBatchStatement;
use App\Services\PaymentGateways\NetcashService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ReconStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public DebitBatchStatement $statement)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(NetcashService $netcash): void
    {
        Cache::lock('netcash-reconciliation-'.$this->statement->debit_batch_id.'-'.$this->statement->polling_id, 4)->block(2, function () use ($netcash) {
            $netcash->reconcileNetcashStatement($this->statement);
        });
    }
}
