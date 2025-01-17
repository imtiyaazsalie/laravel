<?php

namespace App\Jobs\ThreePeaks;

use App\Models\DebitBatch;
use App\Services\PaymentGateways\ThreePeaksService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DebitBatch $debitBatch,
        public ?Carbon $processDateOverride = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(ThreePeaksService $threePeaksService): void
    {
        $threePeaksService->submitDebitBatch($this->debitBatch, $this->processDateOverride);
    }
}
