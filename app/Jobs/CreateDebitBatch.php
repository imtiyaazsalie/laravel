<?php

namespace App\Jobs;

use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\DebitDayDate;
use App\Models\TenantUser;
use App\Services\DebitBatchService;
use App\Services\FinanceService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class CreateDebitBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly int $tenantId, private readonly int $locationId, private readonly DebitDayDate $debitDayDate)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(DebitBatchService $debitBatchService, FinanceService $financeService): void
    {
        $debitBatch = $debitBatchService->getOrCreateDebitBatchForDebitDayDateAndLocation($this->debitDayDate, $this->locationId);

        TenantUser::query()
            ->distinct()
            ->select('user_to_box.*')
            ->join('user_banking_details', function (JoinClause $join) {
                $join->on('user_banking_details.user_id', '=', 'user_to_box.user_id')
                    ->on('user_banking_details.box_id', '=', 'user_to_box.box_id');
            })
            ->whereHas('userLocation', function ($query) {
                $query->active()
                    ->where('box_facility_id', $this->locationId);
            })
            ->where('user_to_box.box_id', '=', $this->tenantId)
            ->where('user_to_box.end_date', '>', today()->toDateString())
            ->where('user_to_box.user_type_id', '=', UserType::GYM_MEMBER)
            ->where('user_status_id', '!=', UserStatus::DEACTIVATED)
            ->where('user_debit_status_id', UserDebitStatus::DEBIT_ORDER)
            ->where('user_banking_details.is_active', '=', true)
            ->where('user_banking_details.debit_day_id', '=', $this->debitDayDate->debit_day_id)
            ->chunkById(300, function ($tenantUsers) use ($debitBatch) {

                $chain = collect();
                $tenantUsers->each(function ($tenantUser) use ($debitBatch, $chain) {
                    $chain->add(new ProcessTenantUserDebitBatch($tenantUser, $debitBatch));
                });

                Bus::batch($chain)->then(function (Batch $batch) {
                    // All jobs completed successfully...
                    // Update some credentials...
                })->catch(function (Batch $batch, \Throwable $e) {
                    // First batch job failure detected...
                })->finally(function (Batch $batch) {
                    // The batch has finished executing...
                })->onQueue('finance')->dispatch();
            });
    }
}
