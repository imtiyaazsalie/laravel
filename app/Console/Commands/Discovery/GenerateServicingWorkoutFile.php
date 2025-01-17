<?php

namespace App\Console\Commands\Discovery;

use App\Enums\DiscoveryVitalityBatchType;
use App\Exports\Discovery\ServiceFileExport;
use App\Jobs\Discovery\UploadServiceFile;
use App\Services\AttendanceRecordService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Excel;

class GenerateServicingWorkoutFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:discovery-generate-servicing-file {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and upload Discovery servicing workout.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today()->setTimezone('Africa/Johannesburg');

        $filename = 'OCTIVworkouts_Servicing'.$date->format('YmdHis').'.csv';

        $serviceFileExport = (new ServiceFileExport())->forDate($date);

        if ($serviceFileExport->query()->doesntExist()) {
            $this->error('No data found for servicing file export.');

            return Command::SUCCESS;
        }

        $serviceFileExport
            ->store(config('discovery.directories.internal.servicing').$filename, 'private', Excel::CSV, ['visibility' => 'private'])
            ->chain([
                new UploadServiceFile($filename),
                function () use ($filename) {
                    (new AttendanceRecordService())->createDiscoveryVitalityBatch($filename, DiscoveryVitalityBatchType::SERVICING_WORKOUT);
                },
            ])->onQueue('exports');

        return Command::SUCCESS;
    }
}
