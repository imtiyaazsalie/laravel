<?php

namespace App\Jobs\ThreePeaks;

use App\Models\DebitBatch;
use App\Services\PaymentGateways\ThreePeaksService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class ReconcileBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public DebitBatch $debitBatch)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(ThreePeaksService $threePeaksService): void
    {
        if ($this->debitBatch->isNotProcessed()) {
            throw new RuntimeException("Trying to reconcile debit batch ID: {$this->debitBatch->getKey()} but is has not yet been processed.");
        }

        $threePeaksService->reconcileDebitBatch($this->debitBatch);
    }
}
