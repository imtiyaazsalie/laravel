<?php

namespace App\Jobs;

use App\Models\DebitBatch;
use App\Models\TenantUser;
use App\Models\UserInvoice;
use App\Services\DebitBatchService;
use App\Services\FinanceService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTenantUserDebitBatch implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public TenantUser $tenantUser,
        public DebitBatch $debitBatch,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! (new DebitBatchService())->shouldTenantUserBeAddedToDebitBatch($this->tenantUser, $this->debitBatch)) {
            return;
        }

        $invoice = (new FinanceService())->generateInvoiceForUser($this->tenantUser->user, $this->debitBatch);

        if (! $invoice instanceof UserInvoice) {
            return;
        }

        (new DebitBatchService())->createUserBatch($this->tenantUser->user, $invoice, $this->debitBatch);

    }
}
