<?php

namespace App\Jobs\Netcash;

use App\Models\DebitBatchStatement;
use App\Services\PaymentGateways\NetcashService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DownloadStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public DebitBatchStatement $debitBatchStatement)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(NetcashService $netcashService): void
    {
        try {
            $result = $netcashService->attemptDownloadNetcashStatement($this->debitBatchStatement);

            if ($result !== true) {
                Log::error($result);
            }
        } catch (Exception $ex) {
            Log::error(get_class($ex).' | '.$ex->getMessage().' - '.$ex->getFile().' on line '.$ex->getLine());
        }
    }
}
