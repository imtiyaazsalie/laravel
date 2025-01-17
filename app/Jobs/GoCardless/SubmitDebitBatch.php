<?php

namespace App\Jobs\GoCardless;

use App\Models\DebitBatch;
use App\Services\PaymentGateways\GoCardlessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SubmitDebitBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(public DebitBatch $debitBatch, public ?Carbon $chargeDateOverride = null)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(GoCardlessService $goCardlessService): void
    {
        $goCardlessService->submitBatch($this->debitBatch, $this->chargeDateOverride);
    }
}
