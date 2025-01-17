<?php

namespace App\Jobs\Netcash;

use App\Models\DebitBatch;
use App\Models\DebitBatchStatement;
use App\Services\PaymentGateways\NetcashService;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RequestStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public DebitBatch $debitBatch)
    {
    }

    /**
     * Process debit batch statements from the latest statement date until yesterday.
     */
    public function handle(NetcashService $netcashService): void
    {
        if ($this->debitBatch->isNotProcessed()) {
            return;
        }

        $latestStatement = DebitBatchStatement::query()->where('debit_batch_id', $this->debitBatch->getKey())->latest('dt')->first();

        $startDate = $latestStatement ? $latestStatement->dt->copy()->addDay() : $this->debitBatch->debitDayDate->debit_day_date;
        $endDate = today()->subDay();

        if ($startDate->greaterThan($endDate)) {
            return;
        }

        $period = new CarbonPeriod($startDate, '1 day', $endDate);

        foreach ($period as $date) {
            $netcashService->requestNetcashStatement($this->debitBatch, $date);
        }
    }
}
