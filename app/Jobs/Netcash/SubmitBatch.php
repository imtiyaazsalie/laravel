<?php

namespace App\Jobs\Netcash;

use App\Models\DebitBatch;
use App\Services\PaymentGateways\NetcashService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SubmitBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public DebitBatch $debitBatch, public Carbon $date, public string $type)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(NetcashService $netcashService): void
    {
        $netcashService->submitNetcashBatch($this->debitBatch, $this->date, $this->type);
    }
}
