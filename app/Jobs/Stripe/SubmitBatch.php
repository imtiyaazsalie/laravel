<?php

namespace App\Jobs\Stripe;

use App\Models\DebitBatch;
use App\Services\PaymentGateways\StripeService;
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
    public function __construct(public DebitBatch $debitBatch)
    {

    }

    /**
     * Execute the job.
     */
    public function handle(StripeService $stripeService): void
    {
        $stripeService->submitDebitBatch($this->debitBatch);
    }
}
