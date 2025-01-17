<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Jobs\CreateDebitBatch;
use App\Models\DebitDayDate;
use App\Models\Location;
use App\Services\DebitBatchService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GenerateDebitBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-debit-batches {--date=} {--tenant-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate debit batches for active box facilities.';

    /**
     * Execute the console command.
     */
    public function handle(DebitBatchService $debitBatchService): int
    {
        $firstDayOfMonth = $this->option('date') ? Carbon::parse($this->option('date'))->startOfMonth() : today()->addMonth()->startOfMonth();

        $this->info('Getting or creating debit day dates for: '.$firstDayOfMonth->format('F Y'), true);

        $debitDayDates = $debitBatchService->createDebitDayDates($firstDayOfMonth);

        Location::query()
            ->joinRelationship('tenant')
            ->where('box_facility.is_active', '=', true)
            ->where('boxes.box_status_id', '=', TenantStatus::ACTIVE)
            ->when($this->option('tenant-id'), function (Builder $query) {
                $query->where('box_facility.box_id', '=', $this->option('tenant-id'));
            })
            ->chunkById(300, function ($locations) use ($debitDayDates) {
                foreach ($locations as $location) {
                    $debitDayDates->each(function (DebitDayDate $debitDayDate) use ($location) {
                        $this->info("Creating debit batch for location ID {$location->getKey()} and date {$debitDayDate->debit_day_date->toDateString()}");

                        CreateDebitBatch::dispatch(
                            tenantId: $location->tenant_id,
                            locationId: $location->getKey(),
                            debitDayDate: $debitDayDate
                        )->onQueue('finance');
                    });
                }
            });

        return Command::SUCCESS;
    }
}
