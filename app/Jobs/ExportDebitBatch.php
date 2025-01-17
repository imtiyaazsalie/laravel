<?php

namespace App\Jobs;

use App\Enums\PaymentGateway;
use App\Models\DebitBatch;
use App\Services\DebitBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExportDebitBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public DebitBatch $debitBatch,
        public string $filePath,
        private readonly ?string $painFormat = null,
        private readonly ?bool $isProcessAsBatch = false)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(DebitBatchService $debitBatchService): void
    {
        $this->debitBatch->loadMissing(['location', 'debitDayDate']);

        $content = null;
        $location = $this->debitBatch->location;

        if ($location->tenant->region->name === 'Namibia') {
            $content = $debitBatchService->generateNamibianDebitBatchExportContent($this->debitBatch);
        } elseif ($location->payment_gateway_id === PaymentGateway::SEPA->value && $location->paymentGateway->is_active) {
            $content = $debitBatchService->generateSepaDebitBatchExportContent($this->debitBatch, $this->painFormat, $this->isProcessAsBatch);
        }

        Storage::disk('tmp')->put($this->filePath, $content);
    }
}
