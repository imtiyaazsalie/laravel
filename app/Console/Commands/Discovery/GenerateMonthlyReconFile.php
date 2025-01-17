<?php

namespace App\Console\Commands\Discovery;

use App\Enums\DiscoveryVitalityBatchType;
use App\Exports\Discovery\MonthlyReconExport;
use App\Jobs\Discovery\UploadMonthlyReconFile;
use App\Services\AttendanceRecordService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Excel;

class GenerateMonthlyReconFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:discovery-generate-monthly-recon-file {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and upload Discover monthly recon.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();

        $filename = 'OctivVitalitybase_'.$date->format('Ymd').'.csv';

        $monthlyReconExport = new MonthlyReconExport();

        if ($monthlyReconExport->query()->doesntExist()) {
            return Command::SUCCESS;
        }

        $monthlyReconExport
            ->store(config('discovery.directories.internal.recon').$filename, 'private', Excel::CSV, ['visibility' => 'private'])
            ->chain([
                new UploadMonthlyReconFile($filename),
                function () use ($filename) {
                    (new AttendanceRecordService())->createDiscoveryVitalityBatch($filename, DiscoveryVitalityBatchType::MONTHLY_RECON);
                },
            ])->onQueue('exports');

        return Command::SUCCESS;
    }
}
